# Copias de seguridad automáticas de la base de datos (PostgreSQL → cifrado → Google Drive)

## Contexto

"Tienda y Librería Israel" es un sistema POS/inventario en Laravel 12 + PostgreSQL. No existe hoy
ningún mecanismo de backup. Se requiere un sistema que:

1. Genere copias de la base de datos PostgreSQL cada 3 días vía Laravel Scheduler (no HTTP, no en
   login).
2. Cifre localmente cada copia antes de subirla a la nube.
3. Suba la copia cifrada a Google Drive.
4. Conserve como máximo las 5 copias exitosas más recientes, tanto en local como en Google Drive.
5. Si una copia falla en cualquier paso (dump, cifrado o subida), no borre ninguna copia existente
   (ni local ni remota).
6. Exponga también un comando manual (`php artisan database:backup`) que use exactamente la misma
   lógica que el proceso automático.

Hallazgos del análisis del proyecto (ver conversación de brainstorming):

- Laravel 12, `bootstrap/app.php` usa `Application::configure()->withSchedule(...)` (API de
  Laravel 11/12), sin scheduler configurado todavía.
- Conexión `pgsql` ya configurada en `.env` (host, puerto, DB, usuario, password).
- No existe `app/Console/Commands/`, `config/backup.php`, ni sistema de backups previo.
- `pg_dump` no está en el PATH de Windows en este entorno; el binario existe en
  `C:\Program Files\PostgreSQL\17\bin\pg_dump.exe`, por lo que la ruta debe ser configurable.
- Disco `local` (`storage/app/private`) es privado por defecto — apropiado para guardar backups.
- No hay SDK PHP maduro para subir a MEGA (se descartó); Google Drive tiene un adapter Flysystem
  razonablemente mantenido: `masbug/flysystem-google-drive-ext`.

## Decisiones (confirmadas con el usuario)

- Proveedor de nube: **Google Drive**, autenticado con una **Service Account** de Google Cloud
  (flujo sin intervención humana, apto para tareas programadas). El usuario no tiene aún la
  Service Account — este documento incluye los pasos para crearla.
- Cifrado: **OpenSSL AES-256-CBC con PBKDF2**, clave dedicada en `.env`
  (`BACKUP_ENCRYPTION_KEY`), independiente de `APP_KEY` (para que rotar `APP_KEY` no invalide
  backups antiguos).
- Retención local: se conservan **ambos** archivos (`.dump` plano y `.dump.enc` cifrado) de las 5
  copias más recientes.
- Retención remota: **misma regla que local** — máximo 5 archivos cifrados en Google Drive; al
  subir el 6º exitosamente se borra el más antiguo en Drive.
- Solo se sube a Drive el archivo **cifrado**; el `.dump` plano nunca sale del servidor.
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
        ├─► GoogleDriveUploader::upload($enc)  → sube a carpeta configurada en Google Drive
        └─► si todo lo anterior tuvo éxito:
              ├─► pruneLocal()   → conserva los 5 pares (.dump + .dump.enc) más recientes
              └─► pruneRemote()  → conserva los 5 archivos .dump.enc más recientes en Drive
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
    'schedule_time' => env('DB_BACKUP_TIME', '02:00'),
    'encryption_key' => env('BACKUP_ENCRYPTION_KEY'),
    'drive' => [
        'credentials_path' => env('GOOGLE_DRIVE_CREDENTIALS_PATH'),
        'folder_id' => env('GOOGLE_DRIVE_BACKUP_FOLDER_ID'),
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

### `app/Services/Backup/GoogleDriveUploader.php` (nuevo)

- Usa `Storage::disk('google')` (disco nuevo en `config/filesystems.php`, driver `google`,
  provisto por `masbug/flysystem-google-drive-ext`) para subir el `.dump.enc` a la carpeta
  `config('backup.drive.folder_id')`.
- Verifica que la subida devuelva éxito y que el archivo remoto exista con tamaño > 0 antes de
  continuar.
- Lanza `UploadFailedException` si falla, sin filtrar el contenido del JSON de credenciales en el
  mensaje de error.

### `app/Services/Backup/DatabaseBackupService.php` (nuevo)

- Método público `run(): BackupResult` que orquesta los pasos descritos en Arquitectura.
- `pruneLocal()`: lista archivos `database_backup_*.dump` en `config('backup.local_path')`,
  ordena por fecha de creación, conserva los `max_backups` más recientes (y su `.enc` asociado),
  borra el resto. Solo toca archivos que matcheen el patrón `database_backup_*`.
- `pruneRemote()`: mismo criterio pero listando `Storage::disk('google')->files($folder)` filtrado
  por el mismo patrón de nombre.
- Todas las llamadas a `Log::info`/`Log::error` registran: inicio, ruta relativa (sin datos
  sensibles), tamaño, cantidad de backups existentes, backups eliminados, errores — nunca
  contraseñas ni claves.

### `app/Console/Commands/DatabaseBackupCommand.php` (nuevo)

- `protected $signature = 'database:backup';`
- Inyecta `DatabaseBackupService`, llama a `run()`, traduce el resultado a mensajes de consola
  (`$this->info(...)` / `$this->error(...)`) y código de salida (0 éxito, 1 fallo).

### `bootstrap/app.php` (modificado)

Se añade `->withSchedule(function (Schedule $schedule) { ... })` (closure real de Laravel 12),
sin tocar el resto del archivo:

```php
->withSchedule(function (Illuminate\Console\Scheduling\Schedule $schedule) {
    $schedule->command('database:backup')
        ->cron('0 2 */3 * *') // cada 3 días a las 02:00; hora ajustable vía DB_BACKUP_TIME
        ->withoutOverlapping();
})
```

(La hora exacta se toma de `config('backup.schedule_time')` para mantenerla configurable sin
tocar código; el cron fijo controla la cadencia de 3 días, ya que Laravel no tiene un helper nativo
`everyThreeDays()`.)

### `.env.example` (modificado) — nuevas variables documentadas y comentadas

```
# PGDUMP_PATH=pg_dump
# OPENSSL_PATH=openssl
# DB_BACKUP_MAX_FILES=5
# DB_BACKUP_TIME=02:00
# BACKUP_ENCRYPTION_KEY=
# GOOGLE_DRIVE_CREDENTIALS_PATH=
# GOOGLE_DRIVE_BACKUP_FOLDER_ID=
```

### `.env` local del usuario (modificado, fuera de git)

- `PGDUMP_PATH="C:\Program Files\PostgreSQL\17\bin\pg_dump.exe"`
- `BACKUP_ENCRYPTION_KEY` generado con `openssl rand -base64 32`.
- `GOOGLE_DRIVE_CREDENTIALS_PATH` apuntando al JSON de la Service Account, guardado fuera de
  `public/`, `resources/` y `app/` (por ejemplo `storage/app/google/credentials.json`, dentro del
  disco privado).
- `GOOGLE_DRIVE_BACKUP_FOLDER_ID` con el ID de la carpeta de Drive destino.

### `config/filesystems.php` (modificado) — nuevo disco

```php
'google' => [
    'driver' => 'google',
    'clientId' => env('GOOGLE_DRIVE_CLIENT_ID'), // no usado con Service Account, se deja null
    'serviceAccount' => env('GOOGLE_DRIVE_CREDENTIALS_PATH'),
    'folderId' => env('GOOGLE_DRIVE_BACKUP_FOLDER_ID'),
],
```

### Dependencia nueva

`composer require masbug/flysystem-google-drive-ext` — único adapter Flysystem 3 (compatible con
Laravel 12) razonablemente mantenido para Google Drive. Sin él no existe forma de usar
`Storage::disk('google')`. Se justifica porque implementar la API de Google Drive a mano
(OAuth2/Service Account, resumable uploads, listar/borrar archivos) reproduciría gran parte de lo
que ya ofrece este paquete de forma probada.

## Manejo de errores

| Fallo | Comportamiento |
|---|---|
| `pg_dump` no instalado / no en PATH | `DumpFailedException`, log de error, comando retorna 1, nada se borra |
| Credenciales de PostgreSQL incorrectas | Igual que arriba; el mensaje de error no incluye la contraseña |
| Falta de permisos de escritura en `storage/app/backups/database` | `DumpFailedException` al crear el directorio/archivo |
| `openssl` falla o no está disponible | `EncryptionFailedException`; el `.dump` plano generado se conserva (no se sube nada sin cifrar) |
| Falla la subida a Google Drive (credenciales, red, cuota) | `UploadFailedException`; no se ejecuta `pruneLocal()` ni `pruneRemote()` |
| Falla el borrado de un backup antiguo durante la limpieza | Se loguea el error puntual; no interrumpe el resultado ya exitoso del backup nuevo |

## Pruebas

`tests/Feature/DatabaseBackupCommandTest.php` y tests unitarios de
`app/Services/Backup/*`, usando `Process::fake()` (para `pg_dump`/`openssl`) y
`Storage::fake('google')` — **sin tocar la base de datos real ni el Google Drive real**:

1. El comando `database:backup` puede ejecutarse manualmente y retorna éxito.
2. El archivo generado sigue el patrón `database_backup_<fecha>_<hora>.dump` (y su `.enc`).
3. El dump usa la conexión `pgsql` (se verifica el comando `pg_dump` invocado).
4. Con 5 backups existentes, al generarse un 6º exitoso se borra solo el más antiguo (local y
   remoto).
5. Si el `pg_dump` simulado falla, no se borra ningún backup existente.
6. Si la subida a Drive simulada falla, no se borra ningún backup existente ni local ni remoto.
7. El directorio de backups no es accesible públicamente (no está bajo `public/`).
8. Los errores se registran vía `Log::` (se puede espiar con `Log::shouldReceive` o
   `Log::spy()`).

No se ejecutan pruebas destructivas contra la base de datos real ni credenciales reales de Google.

## Restauración (documentación, sin ejecutar)

1. Descifrar: `openssl enc -d -aes-256-cbc -pbkdf2 -in database_backup_<ts>.dump.enc -out database_backup_<ts>.dump -pass env:BACKUP_ENC_PASS`
2. Restaurar: `pg_restore --host=<host> --port=<port> --username=<user> --dbname=<db> --clean database_backup_<ts>.dump`

Esto se documentará en el README o en un comentario del comando, pero no se ejecutará como parte de
esta funcionalidad.

## Fuera de alcance (explícito)

- Interfaz frontend, controladores o rutas API para administrar backups.
- Restauración automatizada desde la aplicación.
- Notificaciones (email/Slack) de éxito o fallo — puede añadirse después reutilizando los mismos
  eventos/logs.
