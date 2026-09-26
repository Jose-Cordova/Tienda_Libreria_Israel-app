# Backups de la base de datos

Manual de operación del sistema de copias de seguridad de PostgreSQL de "Tienda y Librería Israel".
Diseño y decisiones: `docs/superpowers/specs/2026-09-23-database-backups-design.md`.

## Qué hace

`php artisan database:backup` ejecuta, en este orden:

1. `pg_dump --format=custom` de la conexión `pgsql` → `database_backup_AAAA-MM-DD_HHMMSS.dump`
2. Cifrado AES-256-CBC + PBKDF2 con OpenSSL → `...dump.enc`
3. Subida del `.enc` a MEGA con rclone (`mega:BackupsBD`)
4. Solo si todo lo anterior salió bien:
   - guarda la fecha en `last_success.txt`
   - borra las copias que excedan el máximo (5), en local y en MEGA

Si cualquier paso falla, **no se borra ninguna copia**, el error queda en
`storage/logs/laravel.log` y el comando termina con código 1.

El Scheduler revisa **cada hora** si pasaron 3 días desde el último backup exitoso. Como la PC se
apaga de noche, el backup se hace en la primera hora en punto con la PC encendida una vez cumplidos
los 3 días. Si la subida falla, se reintenta en la siguiente hora en punto.

## Archivos

| Archivo | Función |
|---|---|
| `config/backup.php` | Configuración (lee variables del `.env`) |
| `app/Console/Commands/DatabaseBackupCommand.php` | Comando `database:backup` |
| `app/Services/Backup/DatabaseBackupService.php` | Orquesta el proceso, limpieza e `isDue()` |
| `app/Services/Backup/DatabaseDumper.php` | Ejecuta `pg_dump` |
| `app/Services/Backup/BackupEncryptor.php` | Cifra con `openssl` |
| `app/Services/Backup/RcloneUploader.php` | Sube, lista y borra en MEGA con `rclone` |
| `app/Services/Backup/Exceptions/*` | Errores de cada paso |
| `routes/console.php` | Programación en el Scheduler |
| `tests/Feature/DatabaseBackupTest.php` | Pruebas automáticas |

Las copias se guardan en `storage/app/backups/database/`, fuera de `public/` y fuera de git
(`storage/app/.gitignore`).

## Configuración (`.env`)

| Variable | Por defecto | Descripción |
|---|---|---|
| `PGDUMP_PATH` | `pg_dump` | Ruta a `pg_dump` si no está en el PATH |
| `OPENSSL_PATH` | `openssl` | Ruta a `openssl` si no está en el PATH |
| `RCLONE_PATH` | `rclone` | Ruta a `rclone` si no está en el PATH |
| `RCLONE_REMOTE` | (obligatoria) | Destino en la nube, p. ej. `mega:BackupsBD` |
| `DB_BACKUP_MAX_FILES` | `5` | Copias exitosas a conservar |
| `DB_BACKUP_EVERY_DAYS` | `3` | Días entre backups automáticos |
| `BACKUP_ENCRYPTION_KEY` | (obligatoria) | Clave de cifrado |

En Windows, las rutas van con **comillas simples**. Con comillas dobles, `\r` de `C:\rclone` se
interpreta como retorno de carro y la ruta se rompe:

```
RCLONE_PATH='C:\rclone\rclone.exe'
```

Generar la clave de cifrado:

```
php -r "echo base64_encode(random_bytes(32));"
```

**Guarda `BACKUP_ENCRYPTION_KEY` también fuera de la PC** (gestor de contraseñas). Sin ella, las
copias de MEGA no se pueden descifrar.

## Uso

Todos los comandos se ejecutan desde la carpeta del backend (`Tienda_Libreria_Isarael-app`).

Backup manual (también reinicia la cuenta de 3 días):

```
php artisan database:backup
```

Ver la programación:

```
php artisan schedule:list
```

`database:backup` aparece como `0 * * * *`: se revisa cada hora; `isDue()` decide si corre.

Ejecutar las pruebas (no tocan la base real ni MEGA):

```
php artisan test --filter=DatabaseBackupTest
```

## Ejecución automática

El Scheduler de Laravel necesita un proceso que lo ejecute.

- **Mientras no esté dockerizado (Windows):** deja abierta una terminal con
  `php artisan schedule:work`. Si esa terminal se cierra, no hay backups automáticos.
- **Con Docker:** un contenedor `scheduler` ejecuta `php artisan schedule:work` (ver abajo).
  Activa en Docker Desktop *"Start Docker Desktop when you sign in"*.

## Verificar que se conservan solo 5 copias

Local:

```
Get-ChildItem storage\app\backups\database
```

MEGA:

```
C:\rclone\rclone.exe lsf mega:BackupsBD
```

Debe haber como máximo 5 archivos `.dump.enc` en cada lugar (más `last_success.txt` en local).
Un intento con la subida fallida deja su par local, que cuenta como copia local.

Revisar el historial en el log:

```
Select-String -Path storage\logs\laravel.log -Pattern 'database:backup' | Select-Object -Last 20
```

## Restaurar en caso de emergencia

No restaures directamente sobre la base en uso sin probar primero.

1. Descarga el `.dump.enc` desde MEGA (o toma el de `storage/app/backups/database`).
2. Carga la clave sin mostrarla en pantalla y descifra (PowerShell, desde el backend):

   ```
   $env:BACKUP_ENC_PASS = (php artisan tinker --execute="echo config('backup.encryption_key');").Trim()
   & 'C:\Program Files\Git\usr\bin\openssl.exe' enc -d -aes-256-cbc -pbkdf2 -in database_backup_X.dump.enc -out restaurar.dump -pass env:BACKUP_ENC_PASS
   Remove-Item Env:BACKUP_ENC_PASS
   ```

3. Comprueba que el archivo es válido (solo lee el archivo, no se conecta a la base):

   ```
   & 'C:\Program Files\PostgreSQL\17\bin\pg_restore.exe' --list restaurar.dump
   ```

4. Restaura primero en una base **nueva** y revisa los datos:

   ```
   & 'C:\Program Files\PostgreSQL\17\bin\createdb.exe' -h 127.0.0.1 -U <usuario> tienda_restaurada
   & 'C:\Program Files\PostgreSQL\17\bin\pg_restore.exe' -h 127.0.0.1 -U <usuario> -d tienda_restaurada --no-owner restaurar.dump
   ```

5. Solo si es correcto, detén la aplicación y reemplaza la base original:

   ```
   & 'C:\Program Files\PostgreSQL\17\bin\pg_restore.exe' -h 127.0.0.1 -U <usuario> -d <base_original> --clean --if-exists --no-owner restaurar.dump
   ```

6. Borra `restaurar.dump`: es una copia sin cifrar de toda la base.

## Requisitos para Docker

Arquitectura prevista: frontend y Laravel en contenedores; PostgreSQL 17 instalado en Windows,
fuera de Docker. El código del backup no cambia; cambia el entorno. Validar al dockerizar.

**Imagen de Laravel:**

- `postgresql-client-17` desde el repositorio oficial de PostgreSQL (PGDG). Debian trae la
  versión 15 y `pg_dump` se niega a respaldar un servidor más nuevo.
- `openssl` y `rclone` instalados y en el PATH.

```dockerfile
RUN apt-get update && apt-get install -y curl ca-certificates unzip openssl \
 && install -d /usr/share/postgresql-common/pgdg \
 && curl -fsSL -o /usr/share/postgresql-common/pgdg/apt.postgresql.org.asc https://www.postgresql.org/media/keys/ACCC4CF8.asc \
 && echo "deb [signed-by=/usr/share/postgresql-common/pgdg/apt.postgresql.org.asc] https://apt.postgresql.org/pub/repos/apt bookworm-pgdg main" > /etc/apt/sources.list.d/pgdg.list \
 && apt-get update && apt-get install -y postgresql-client-17 \
 && curl -fsSL https://rclone.org/install.sh | bash \
 && rm -rf /var/lib/apt/lists/*
```

**Servicio `scheduler` en `docker-compose.yml`** (misma imagen que Laravel):

```yaml
scheduler:
  image: <imagen-de-laravel>
  command: php artisan schedule:work
  restart: unless-stopped
  env_file: .env
  extra_hosts:
    - "host.docker.internal:host-gateway"
  volumes:
    - C:/TiendaIsrael/backups:/var/www/html/storage/app/backups
```

Backup manual dentro de Docker:

```
docker compose exec scheduler php artisan database:backup
```

**`.env` del contenedor:**

- `DB_HOST=host.docker.internal`
- Sin `PGDUMP_PATH`, `OPENSSL_PATH` ni `RCLONE_PATH` (se usan los del PATH de la imagen).
- MEGA por variables de entorno, sin montar `rclone.conf`:

  ```
  RCLONE_CONFIG_MEGA_TYPE=mega
  RCLONE_CONFIG_MEGA_USER=<correo de MEGA>
  RCLONE_CONFIG_MEGA_PASS=<salida de: rclone obscure "contraseña">
  RCLONE_REMOTE=mega:BackupsBD
  ```

  `rclone obscure` no es cifrado real: trata ese valor como una contraseña.

**PostgreSQL en Windows** debe aceptar conexiones desde la red de Docker (`listen_addresses` en
`postgresql.conf` y una regla en `pg_hba.conf`).

**Volumen:** las copias deben vivir en una carpeta de Windows montada, para no perderlas al
recrear el contenedor.

## Problemas comunes

| Síntoma | Causa probable |
|---|---|
| `Could not open input file: artisan` | Se ejecutó en la carpeta del frontend (`-ft`) |
| `rclone no pudo subir el archivo` y en el log `"C:cloneclone.exe" no se reconoce` | Ruta de rclone con comillas dobles en `.env` |
| `pg_dump finalizó con error` | PostgreSQL detenido, credenciales o `PGDUMP_PATH` incorrectos, o `pg_dump` más viejo que el servidor |
| `RCLONE_REMOTE no está configurado` | Falta `RCLONE_REMOTE` en `.env` |
| No hay backups automáticos | No hay ningún proceso ejecutando `schedule:work` / `schedule:run` |
| Fallo al iniciar sesión en MEGA | La cuenta tiene 2FA activado (no compatible) o cambió la contraseña |
