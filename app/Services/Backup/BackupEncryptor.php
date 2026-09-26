<?php

namespace App\Services\Backup;

use App\Services\Backup\Exceptions\EncryptionFailedException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

//Cifra un archivo de dump con OpenSSL (AES-256-CBC + PBKDF2).
class BackupEncryptor
{
    //Cifra $dumpPath y devuelve la ruta del archivo .enc generado
    public function encrypt(string $dumpPath): string
    {
        $encryptedPath = $dumpPath.'.enc';

        //La llave se pasa por la variable de entorno, nunca como argumento visible
        $result = Process::env(['BACKUP_ENC_PASS' => config('backup.encryption_key')])
            ->run([
                config('backup.openssl_path'),
                'enc',
                '-aes-256-cbc',
                '-pbkdf2',
                '-salt',
                '-in', $dumpPath,
                '-out', $encryptedPath,
                '-pass', 'env:BACKUP_ENC_PASS'
            ]);

        //Si openssl falla se lanza una excepcion
        if(! $result->successful()){
            Log::error('database:backup - fallo al cifrar el dump', [
                'exit_code' => $result->exitCode(),
            ]);
            throw new EncryptionFailedException('openssl finalizó con error.');
        }

        //Verifcar que el archivo cifado exista y tenga contenido
        if(! File::exists($encryptedPath) || File::size($encryptedPath) === 0){
            throw new EncryptionFailedException('El archivo cifrado no se generó o está vacío.');
        }

        Log::info('database:backup - dump cifrado correctamente', [
            'file' => basename($encryptedPath),
            'size' => File::size($encryptedPath),
        ]);

        return $encryptedPath;
    }
}
