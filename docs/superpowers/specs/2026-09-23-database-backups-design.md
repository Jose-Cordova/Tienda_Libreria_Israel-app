# Copias de seguridad automáticas de la base de datos (PostgreSQL → cifrado → MEGA)

## Contexto

"Tienda y Librería Israel" es un sistema POS/inventario en Laravel 12 + PostgreSQL. No existe hoy
ningún mecanismo de backup. Se requiere un sistema que:

1. Genere copias de la base de datos PostgreSQL cada 3 días vía Laravel Scheduler (no HTTP, no en
   login). La PC que aloja el sistema se apaga de noche, así que el backup se recupera en la
   primera hora en que la PC esté encendida una vez cumplidos los 3 días.
2. Cifre localmente cada copia antes de subirla a la nube.
3. Suba la copia cifrada a MEGA.
4. Conserve como máximo las 5 copias exitosas más recientes, tanto en local como en MEGA.
5. Si una copia falla en cualquier paso (dump, cifrado o subida), no borre ninguna copia existente
   (ni local ni remota).
6. Exponga también un comando manual (`php artisan database:backup`) que use exactamente la misma
   lógica que el proceso automático.

Hallazgos del análisis del proyecto (ver conversación de brainstorming):

- Laravel 12. El proyecto ya programa tareas en `routes/console.php` con
  `Schedule::command(...)` (`inventario:procesar-vencidos`); el backup sigue esa convención.
  `bootstrap/app.php` no se modifica.
- Conexión `pgsql` ya configurada en `.env` (host, puerto, DB, usuario, password).
- No existe `app/Console/Commands/`, `config/backup.php`, ni sistema de backups previo.
- `pg_dump` no está en el PATH de Windows en este entorno; el binario existe en
  `C:\Program Files\PostgreSQL\17\bin\pg_dump.exe`, por lo que la ruta debe ser configurable.
- Disco `local` (`storage/app/private`) es privado por defecto — apropiado para guardar backups.
- Se descartaron los SDK PHP para la nube (MEGA sin SDK maduro; Google Drive exige proyecto de
  Google Cloud, y el cliente compartido de rclone para Drive se retira durante 2026). Se usa la
  CLI gratuita **rclone**, ejecutada con `Process` igual que `pg_dump` y `openssl`.

## Decisiones (confirmadas con el usuario)

- Proveedor de nube: **MEGA** (20 GB gratis) vía **rclone**, remote `mega:BackupsBD` configurado
  una sola vez con `rclone config`. La cuenta no debe tener 2FA (rclone inicia sesión en cada
  ejecución y el código 2FA cambia cada 30 s). Se descartó Google Drive (Service Account sin
  cuota en cuentas personales; cliente compartido de rclone en retiro durante 2026).
- Cifrado: **OpenSSL AES-256-CBC con PBKDF2**, clave dedicada en `.env`
  (`BACKUP_ENCRYPTION_KEY`), independiente de `APP_KEY` (para que rotar `APP_KEY` no invalide
  backups antiguos).
- Retención local: se conservan **ambos** archivos (`.dump` plano y `.dump.enc` cifrado) de las 5
  copias más recientes.
- Retención remota: **misma regla que local** — máximo 5 archivos cifrados en MEGA; al
  subir el 6º exitosamente se borra el más antiguo en MEGA.
- Solo se sube a MEGA el archivo **cifrado**; el `.dump` plano nunca sale del servidor.
- No se construye interfaz frontend, ruta API ni controlador para esta funcionalidad en esta
  primera versión.

## Alternativas consideradas

- **Todo en el Command (sin servicios separados):** descartado — mezclaría dump/cifrado/subida/
  retención en un método largo, dificultando pruebas aisladas y violando el requisito de que el
  comando manual y el automático compartan exactamente la misma lógica (aquí ambos llaman al mismo
  servicio, no se duplica nada).
- **`spatie/laravel-backup`:** descartado — su estrategia de retención es por *rango de días*
  (`keepAllBackupsForXDays`, `keepDailyBackupsForXDays`, ...), no por *conteo exacto* de copias
  exitosas, que es un requisito explícito y preciso del usuario ("conservar exactamente las
  últimas 5"). Adaptar spatie/laravel-backup a esa semántica añadiría tanta complejidad de
  configuración como escribir la lógica a medida, sin ganar precisión ni control sobre el
  comportamiento "no borrar nada si falla".

## Arquitectura

```
DatabaseBackupCommand (php artisan database:backup)
        │
        ▼
DatabaseBackupService::run()
        │
        ├─► DatabaseDumper::dump()            → storage/app/backups/database/database_backup_<ts>.dump
        ├─► BackupEncryptor::encrypt($dump)    → storage/app/backups/database/database_backup_<ts>.dump.enc
        ├─► RcloneUploader::upload($enc)       → sube a la carpeta remota configurada (MEGA)
        └─► si todo lo anterior tuvo éxito:
              ├─► pruneLocal()   → conserva los 5 pares (.dump + .dump.enc) más recientes
              └─► pruneRemote()  → conserva los 5 archivos .dump.enc más recientes en MEGA
```

Cada paso lanza una excepción específica si falla; el servicio detiene el flujo en el primer
fallo, **no ejecuta limpieza** y registra el error en logs sin credenciales ni contraseñas.

## Componentes

### `config/backup.php` (nuevo)

```php
return [
    'pg_dump_path' => env('PGDUMP_PATH', 'pg_dump'),
    'openssl_path' => env('OPENSSL_PATH', 'openssl'),
    'max_backups' => (int) env('DB_BACKUP_MAX_FILES', 5),
    'local_path' => storage_path('app/backups/database'),
    'every_days' => (int) env('DB_BACKUP_EVERY_DAYS', 3),
    'encryption_key' => env('BACKUP_ENCRYPTION_KEY'),
    'drive' => [
        'rclone_path' => env('RCLONE_PATH', 'rclone'),
        'rclone_remote' => env('RCLONE_REMOTE'), // ej. mega:BackupsBD
    ],
];
```

### `app/Services/Backup/DatabaseDumper.php` (nuevo)

- Lee la conexión `pgsql` desde `config('database.connections.pgsql')`.
- Construye el nombre `database_backup_{Y-m-d_His}.dump` (evita colisiones y nombres genéricos).
- Ejecuta `pg_dump` vía `Symfony\Component\Process\Process` con `--format=custom` (formato apto
  para restaurar con `pg_restore`), pasando `PGPASSWORD` como variable de entorno del subproceso
  (nunca como argumento de línea de comandos, nunca en logs).
- Verifica: exit code 0, archivo existe, tamaño > 0.
- Lanza `DumpFailedException` con mensaje sin credenciales si algo falla.

### `app/Services/Backup/BackupEncryptor.php` (nuevo)

- Cifra el `.dump` con `openssl enc -aes-256-cbc -pbkdf2 -salt -pass env:BACKUP_ENC_PASS` (la clave
  se inyecta como variable de entorno del subproceso, igual patrón que `PGPASSWORD`).
- Produce `<mismo-nombre>.dump.enc`.
- Verifica exit code 0 y tamaño > 0. Lanza `EncryptionFailedException` si falla.

### `app/Services/Backup/RcloneUploader.php` (nuevo)

- Sube el `.dump.enc` con `rclone copyto <archivo> <remote>/<nombre>` vía `Process`, usando
  `config('backup.drive.rclone_path')` y `config('backup.drive.rclone_remote')`.
- Verifica exit code 0 y que el archivo exista en el remoto (`rclone lsf`) antes de continuar.
- Expone también listar (`rclone lsf`) y borrar (`rclone deletefile`) para `pruneRemote()`.
- Lanza `UploadFailedException` si falla, sin filtrar datos sensibles en el mensaje de error.

### `app/Services/Backup/DatabaseBackupService.php` (nuevo)

- Método público `run(): array` que orquesta los pasos descritos en Arquitectura y, tras una
  subida exitosa, escribe la fecha en `last_success.txt` (archivo de control).
- Método público `isDue(): bool`: indica si pasaron `every_days` días desde `last_success.txt`
  (sin archivo o con contenido dañado devuelve `true`).
- `pruneLocal()`: lista archivos `database_backup_YYYY-MM-DD_HHMMSS.dump` en
  `config('backup.local_path')`, los ordena por nombre (el nombre contiene la fecha, más fiable que
  la fecha de modificación), borra `.dump` sin su `.enc` (intentos fallidos de cifrado), conserva
  los `max_backups` más recientes con su `.enc` y borra el resto. Solo toca archivos que coinciden
  exactamente con ese patrón.
- `pruneRemote()`: mismo criterio pero listando el remoto con `RcloneUploader` y filtrando por el
  mismo patrón de nombre.
- Todas las llamadas a `Log::info`/`Log::error` registran: inicio, ruta relativa (sin datos
  sensibles), tamaño, cantidad de backups existentes, backups eliminados, errores — nunca
  contraseñas ni claves.

### `app/Console/Commands/DatabaseBackupCommand.php` (nuevo)

- `protected $signature = 'database:backup';`
- Inyecta `DatabaseBackupService`, llama a `run()`, traduce el resultado a mensajes de consola
  (`$this->info(...)` / `$this->error(...)`) y código de salida (0 éxito, 1 fallo).

### `routes/console.php` (modificado)

Sigue la convención existente del proyecto:

```php
Schedule::command('database:backup')
    ->hourly()
    ->when(fn () => app(DatabaseBackupService::class)->isDue())
    ->withoutOverlapping();
```

Se descartaron un cron fijo `0 2 */3 * *` (se reinicia cada mes y, con la PC apagada de noche,
nunca correría) y una hora fija diaria. La revisión cada hora más `isDue()` hace el backup en la
primera hora con la PC encendida una vez cumplidos los días; si la subida falla, `last_success.txt`
no cambia y se reintenta en la siguiente hora en punto.

### `.env.example` (modificado)

Variables documentadas: `PGDUMP_PATH`, `OPENSSL_PATH`, `RCLONE_PATH`, `RCLONE_REMOTE`,
`DB_BACKUP_MAX_FILES`, `DB_BACKUP_EVERY_DAYS`, `BACKUP_ENCRYPTION_KEY`. Las rutas deben ir
**comentadas**: fuera de Windows (Docker/Linux) los valores por defecto `pg_dump`, `openssl` y
`rclone` del PATH son los correctos.

### `.env` local del usuario (modificado, fuera de git)

- Rutas de Windows con **comillas simples**. Con comillas dobles, phpdotenv interpreta `\r` como
  retorno de carro y `"C:\rclone\rclone.exe"` se convierte en una ruta rota (bug encontrado en la
  primera ejecución real).
- `PGDUMP_PATH='C:\Program Files\PostgreSQL\17\bin\pg_dump.exe'`,
  `OPENSSL_PATH='C:\Program Files\Git\usr\bin\openssl.exe'`, `RCLONE_PATH='C:\rclone\rclone.exe'`,
  `RCLONE_REMOTE=mega:BackupsBD`.
- `BACKUP_ENCRYPTION_KEY` generado con `php -r "echo base64_encode(random_bytes(32));"`.

### Dependencias

Ningún paquete de Composer. Se requiere instalar la herramienta externa **rclone** en el servidor
y ejecutar una vez `rclone config` para configurar MEGA. Sin cambios en
`config/filesystems.php`.

## Manejo de errores

| Fallo | Comportamiento |
|---|---|
| `pg_dump` no instalado / no en PATH | `DumpFailedException`, log de error, comando retorna 1, nada se borra |
| Credenciales de PostgreSQL incorrectas | Igual que arriba; el mensaje de error no incluye la contraseña |
| Falta de permisos de escritura en `storage/app/backups/database` | `DumpFailedException` al crear el directorio/archivo |
| `openssl` falla o no está disponible | `EncryptionFailedException`; el `.dump` plano generado se conserva (no se sube nada sin cifrar) |
| Falla la subida a MEGA (credenciales, red, cuota) | `UploadFailedException`; no se ejecuta `pruneLocal()` ni `pruneRemote()` |
| Falla el borrado de un backup antiguo durante la limpieza | Se loguea el error puntual; no interrumpe el resultado ya exitoso del backup nuevo |

## Pruebas

Implementadas en `tests/Feature/DatabaseBackupTest.php` (10 pruebas, 82 comprobaciones, todas
pasan), usando `Process::fake()` (para `pg_dump`, `openssl` y `rclone`) y un
directorio temporal local — **sin tocar la base de datos real ni el MEGA real**:

1. El comando `database:backup` puede ejecutarse manualmente y retorna éxito.
2. El archivo generado sigue el patrón `database_backup_<fecha>_<hora>.dump` (y su `.enc`).
3. El dump usa la conexión `pgsql` (se verifica el comando `pg_dump` invocado).
4. Con 5 backups existentes, al generarse un 6º exitoso se borra solo el más antiguo (local y
   remoto).
5. Si el `pg_dump` simulado falla, no se borra ningún backup existente.
6. Si la subida a MEGA simulada falla, no se borra ningún backup existente ni local ni remoto.
7. El directorio de backups no es accesible públicamente (no está bajo `public/`).
8. Los errores se registran vía `Log::` (se puede espiar con `Log::shouldReceive` o
   `Log::spy()`).

No se ejecutan pruebas destructivas contra la base de datos real ni credenciales reales de MEGA.

## Restauración (documentación, sin ejecutar)

1. Descifrar: `openssl enc -d -aes-256-cbc -pbkdf2 -in database_backup_<ts>.dump.enc -out database_backup_<ts>.dump -pass env:BACKUP_ENC_PASS`
2. Restaurar: `pg_restore --host=<host> --port=<port> --username=<user> --dbname=<db> --clean database_backup_<ts>.dump`

El procedimiento completo de operación y restauración está en `docs/backups-base-de-datos.md`.

## Fuera de alcance (explícito)

- Interfaz frontend, controladores o rutas API para administrar backups.
- Restauración automatizada desde la aplicación.
- Notificaciones (email/Slack) de éxito o fallo — puede añadirse después reutilizando los mismos
  eventos/logs.
