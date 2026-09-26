<?php

namespace App\Services\Backup;

use App\Services\Backup\Exceptions\UploadFailedException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

//Sube, lista y borra backups cifrados en la nube usando rclone
class RcloneUploader
{
    //Sube el archivo cifrado al remoto y verifica que existe
    public function upload(string $encryptedPath): void
    {
        $name = basename($encryptedPath);
        $result = $this->rclone(['copyto', $encryptedPath, $this->remotePath($name)]);

        if(! $result->successful()){
            Log::error('database:backup - fallo al subir a la nube', [
                'exit_code' => $result->exitCode(),
            ]);
            throw new UploadFailedException('rclone no pudo subir el archivo.');
        }

        //Confirmar que el archivo quedo en el remoto
        if(! in_array($name, $this->listBackups(), true)){
            throw new UploadFailedException('El archivo no aparece en la nube tras la subida.');
        }
        Log::info('database:backup - backup subido a la nube', ['file' => $name]);
    }

    //Devuelbe los nombres de backups del mas antiguo al mas reciente
    public function listBackups(): array
    {
        $result = $this->rclone(['lsf', $this->remote(), '--files-only']);

        if(! $result->successful()){
            Log::error('database:backup - fallo al listar la nube', [
                'exit_code' => $result->exitCode(),
            ]);
            throw new UploadFailedException('rclone no pudo listar los backups.');
        }

        //Solo archivos que pertenecen al sistema de backups
        $names = array_filter(
            array_map('trim', explode("\n", $result->output())),
            fn ($name) => preg_match('/^database_backup_.+\.dump\.enc$/', $name) === 1
        );

        //El nombre lleva la fecha (Y-m-d_His)
        sort($names);
        return array_values($names);
    }

    //Borrar un backup del remoto
    public function delete(string $name): void
    {
        $result = $this->rclone(['deletefile', $this->remotePath($name)]);

        if(! $result->successful()){
            Log::error('database:backup - fallo al borrar backup en la nube', [
                'file' => $name,
                'exit_code' => $result->exitCode(),
            ]);
            throw new UploadFailedException('rclone no pudo borrar el archivo remoto.');
        }
    }

    //Ejecuta rclone con argumentos separados
    private function rclone(array $arguments)
    {
        return Process::run([config('backup.drive.rclone_path'), ...$arguments]);
    }

    //Devuelve el destino remoto configurado, ej. mega:BackupsBD
    private function remote(): string
    {
        $remote = config('backup.drive.rclone_remote');

        if(empty($remote)){
            throw new UploadFailedException('RCLONE_REMOTE no está configurado en el .env.');
        }

        return rtrim($remote, '/');
    }

    //Devuelve la ruta remota completa de un archivo
    private function remotePath(string $name): string
    {
        return $this->remote().'/'.$name;
    }
}
