<?php

namespace App\Services\Backup;

use App\Services\Backup\Exceptions\DumpFailedException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

//Genera el dump de la base de datos PostgreSQL usando pg_dump
class DatabaseDumper
{
    //Crea el dump y devuelve la ruta completa del archivo generado
    public function dump(): string
    {
        $directory = config('backup.local_path');

        //Crea el directorio si no existe
        File::ensureDirectoryExists($directory);

        $fileName = 'database_backup_'.now()->format('Y-m-d_His').'.dump';
        $filePath = $directory.DIRECTORY_SEPARATOR.$fileName;

        $connection = config('database.connections.pgsql');

        //Ejecuta pg_dump  pasando la contraseña por variable de entorno
        $result = Process::env(['PGPASSWORD' => $connection['password']])
        ->run([
            config('backup.pg_dump_path'),
            '--host='.$connection['host'],
            '--port='.$connection['port'],
            '--username='.$connection['username'],
            '--format=custom',
            '--file='.$filePath,
            $connection['database']
        ]);

        //Si pg_dump falla se lanza una excepcion
        if(! $result->successful()){
            Log::error('database:backup - fallo al ejecutar pg_dump', [
                'exit_code' => $result->exitCode(),
            ]);
            throw new DumpFailedException('pg_dump finalizó con error.');
        }

        //Verifica el archivo exista y tenga contenido
        if(! File::exists($filePath) || File::size($filePath) === 0){
            throw new DumpFailedException('El archivo de dump no se generó o está vacío.');
        }

        //Registra exito
        Log::info('database:backup - dump generado correctamente', [
            'file' => $fileName,
            'size' => File::size($filePath),
        ]);

        return $filePath;
    }
}
