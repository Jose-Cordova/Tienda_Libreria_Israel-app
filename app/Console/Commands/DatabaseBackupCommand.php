<?php

namespace App\Console\Commands;

use App\Services\Backup\DatabaseBackupService;
use Illuminate\Console\Command;
use Throwable;

//Comando programado para generar el backup
class DatabaseBackupCommand extends Command
{
    protected $signature = 'database:backup';
    protected $description = 'Genera un backup cifrado de la base de datos y lo sube a la nube';

    //Delegar toda la logica al servicio y solo mostrar los resultados
    public function handle(DatabaseBackupService $service): int
    {
        $this->info('Iniciando backup de la base de datos...');

        try{
            $result = $service->run();
        }catch(Throwable $e){
            $this->error('El backup falló: '.$e->getMessage());
            $this->line('Revisa storage/logs/laravel.log. No se borró ninguna copia anterior.');

            //Código de salida distinto de 0 para que el Scheduler lo detecte como fallo
            return self::FAILURE;
        }

        $this->info('Backup completado: '.$result['file'].' ('.number_format($result['size'] / 1048576, 2).' MB)');
        $this->line('Copias locales eliminadas: '.count($result['deleted_local']));
        $this->line('Copias en la nube eliminadas: '.count($result['deleted_remote']));

        return self::SUCCESS;
    }
}
