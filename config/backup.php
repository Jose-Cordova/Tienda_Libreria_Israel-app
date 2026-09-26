<?php

//Configuración del sistema de backups de la base de datos
return [
    'pg_dump_path' => env('PGDUMP_PATH', 'pg_dump'), //Ruta al ejecutable de pg_dump
    'openssl_path' => env('OPENSSL_PATH', 'openssl'), //Ruta al ejecutable de openssl
    'max_backups' => (int) env('DB_BACKUP_MAX_FILES', 5), //Máximo de copias exitosas
    'local_path' => storage_path('app/backups/database'), //Carpeta privada donde se guardan los backups
    'every_days' => (int) env('DB_BACKUP_EVERY_DAYS', 3), //Cada cuántos días se hace el backup automático
    'encryption_key' => env('BACKUP_ENCRYPTION_KEY'), //Clave de cifrado, independiente de APP_KEY

    //Datos de conexión con la nube (rclone)
    'drive' => [
        'rclone_path' => env('RCLONE_PATH', 'rclone'), //Ruta al ejecutable de rclone
        'rclone_remote' => env('RCLONE_REMOTE') //Destino remoto, ej. mega:BackupsBD
    ]
];
