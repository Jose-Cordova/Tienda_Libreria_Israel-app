<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;
use Throwable;

//Orquesta el backup: dump -> cifrado -> subida -> limpieza de copias antiguas
class DatabaseBackupService
{
    public function __construct(
        private DatabaseDumper $dumper,
        private BackupEncryptor $encryptor,
        private RcloneUploader $uploader,
    ){

    }

    //Ejecuta el backup completo y devuelve un resumen del resultado
    public function run(): array
    {
        Log::info('database:backup - inicio del backup');

        try{
            $dumpPath = $this->dumper->dump();
            $encryptedPath = $this->encryptor->encrypt($dumpPath);
            $this->uploader->upload($encryptedPath);
            $this->markSuccess();
        }catch(Throwable $e) {
            //Si algo falla no se limpia nada: las copias anteriores quedan intactas
            Log::error('database:backup - backup fallido, no se borró ninguna copia', [
                'error' => get_class($e).': '.$e->getMessage(),
            ]);
            throw $e;
        }

        //Solo llegamos aquí si todo salió bien, ahora se limpian las copias antiguas
        $deletedLocal = $this->pruneLocal();
        $deletedRemote = $this->pruneRemote();

        Log::info('database:backup - backup completado', [
            'file' => basename($encryptedPath),
            'size' => File::size($encryptedPath),
            'deleted_local' => $deletedLocal,
            'deleted_remote' => $deletedRemote,
        ]);

        return [
            'file' => basename($encryptedPath),
            'size' => File::size($encryptedPath),
            'deleted_local' => $deletedLocal,
            'deleted_remote' => $deletedRemote,
        ];
    }

    //Conserva los N backups locales más recientes y borra el resto
    private function pruneLocal(): array
    {
        $directory = config('backup.local_path');
        $max = config('backup.max_backups');
        $deleted = [];

        //Solo archivos con el nombre exacto que genera este sistema
        $dumps = collect(File::glob($directory.DIRECTORY_SEPARATOR.'database_backup_*.dump'))
            ->filter(fn ($path) => preg_match('/^database_backup_\d{4}-\d{2}-\d{2}_\d{6}\.dump$/', basename($path)) === 1)
            ->sort()
            ->values();

        //Un .dump sin su .enc es un intento fallido
        [$valid, $orphans] = $dumps->partition(fn ($path) => File::exists($path.'.enc'));

        foreach ($orphans as $path) {
            $this->deleteLocal($path, $deleted);
        }

        //Las copias válidas más antiguas que superen el máximo se eliminan (.dump y .enc)
        foreach ($valid->slice(0, max(0, $valid->count() - $max)) as $path) {
            $this->deleteLocal($path, $deleted);
            $this->deleteLocal($path.'.enc', $deleted);
        }

        return $deleted;
    }

    //Conserva los N backups más recientes en la nube y borra el resto
    private function pruneRemote(): array
    {
        $deleted = [];

        try{
            $names = $this->uploader->listBackups();

            foreach (array_slice($names, 0, max(0, count($names) - config('backup.max_backups'))) as $name) {
                $this->uploader->delete($name);
                $deleted[] = $name;
            }
        }catch(Throwable $e){
            //Un fallo al limpiar no invalida el backup que ya se subió bien
            Log::error('database:backup - error al limpiar copias antiguas en la nube', [
                'error' => $e->getMessage(),
            ]);
        }

        return $deleted;
    }

    //Borra un archivo local y registra el nombre
    private function deleteLocal(string $path, array &$deleted): void
    {
        if(File::exists($path) && File::delete($path)){
            $deleted[] = basename($path);
        }elseif(File::exists($path)){
            Log::error('database:backup - no se pudo borrar backup local', ['file' => basename($path)]);
        }
    }

    //Indica si va a ser backup segun la fecha del ultimo backup exitoso
    public function isDue(): bool
    {
        //Sin backup exitoso previo se hece el primero
        if(! File::exists($this->markerPath())){
            return true;
        }

        try{
            $ultimo = Carbon::createFromFormat('Y-m-d', trim(File::get($this->markerPath())))->startOfDay();
        }catch(Throwable $e){
            //Si el archivo esta daniado se hece el backup por seguridad
            return true;
        }

        return $ultimo->diffInDays(today()) >= config('backup.every_days');
    }

    //Guarda la fecha del ultimo backup exitoso para calcular el siguiente
    private function markSuccess(): void
    {
        File::put($this->markerPath(), now()->toDateString());
    }

    //Ruta del archivo con la fecha del último backup subido a la nube
    private function markerPath(): string
    {
        return config('backup.local_path').DIRECTORY_SEPARATOR.'last_success.txt';
    }
}
