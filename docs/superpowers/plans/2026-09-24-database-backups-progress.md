# Backups de base de datos: estado final

Funcionalidad terminada el 2026-09-26.

- Manual de operación, restauración y requisitos de Docker: `docs/backups-base-de-datos.md`
- Diseño y decisiones: `docs/superpowers/specs/2026-09-23-database-backups-design.md`

## Estado

| # | Paso | Archivo | Estado |
|---|---|---|---|
| 1 | Configuración | `config/backup.php` | Hecho |
| 2 | Dump | `app/Services/Backup/DatabaseDumper.php` + excepción | Hecho |
| 3 | Cifrado | `app/Services/Backup/BackupEncryptor.php` + excepción | Hecho |
| 4 | Subida a MEGA | `app/Services/Backup/RcloneUploader.php` + excepción | Hecho |
| 5 | Orquestador, limpieza e `isDue()` | `app/Services/Backup/DatabaseBackupService.php` | Hecho |
| 6 | Comando | `app/Console/Commands/DatabaseBackupCommand.php` | Hecho |
| 7 | Scheduler (cada hora + `isDue()`) | `routes/console.php` | Hecho |
| 8 | Variables documentadas | `.env.example` | Hecho (pendiente: comentar las rutas de Windows antes de dockerizar) |
| 9 | Pruebas | `tests/Feature/DatabaseBackupTest.php` | 10/10 pasan (82 comprobaciones) |
| 10 | Documentación | `docs/backups-base-de-datos.md` | Hecho |

## Verificación real (2026-09-24)

- `php artisan database:backup` generó, cifró y subió `database_backup_2026-09-24_211843.dump.enc`
  (107 KB) a `mega:BackupsBD`.
- El `.enc` descifrado es idéntico byte a byte al `.dump`, y `pg_restore --list` lo lee sin
  errores (38 tablas con datos).

## Pendientes fuera de este trabajo

- Mientras no haya Docker, los backups automáticos solo corren con `php artisan schedule:work`
  abierto en una terminal.
- Dockerización (tarea aparte): contenedor `scheduler`, `postgresql-client-17`, rclone por
  variables de entorno, volumen de backups, acceso de Docker a PostgreSQL de Windows.
- Nada de esto está commiteado todavía.

## Forma de trabajo acordada

Código paso a paso en el chat; el usuario lo aplica y se revisa después. Comentarios de una línea.
Los comandos `php artisan` se ejecutan en `Tienda_Libreria_Isarael-app`, no en el frontend `-ft`.
