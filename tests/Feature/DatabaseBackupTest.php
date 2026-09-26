<?php

namespace Tests\Feature;

use App\Services\Backup\DatabaseBackupService;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Tests\TestCase;

//Pruebas del backup: todos los procesos son simulados, no se toca la BD real ni MEGA
class DatabaseBackupTest extends TestCase
{
    private string $dir;

    //Archivos que "existen" en la nube simulada
    private array $remote = [];

    protected function setUp(): void
    {
        parent::setUp();

        //Carpeta temporal propia de cada prueba, nunca la carpeta real de backups
        $this->dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'backup_test_'.uniqid();
        File::ensureDirectoryExists($this->dir);

        config([
            'backup.local_path' => $this->dir,
            'backup.pg_dump_path' => 'pg_dump',
            'backup.openssl_path' => 'openssl',
            'backup.drive.rclone_path' => 'rclone',
            'backup.drive.rclone_remote' => 'mega:Pruebas',
            'backup.max_backups' => 5,
            'backup.every_days' => 3,
            'backup.encryption_key' => 'clave-de-prueba',
            'logging.default' => 'null', //Las pruebas no escriben en laravel.log
        ]);

        //Fecha fija para que los nombres de archivo sean predecibles
        $this->travelTo('2026-09-26 10:00:00');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);

        parent::tearDown();
    }

    //1 y 2: el comando manual genera el dump, lo cifra, lo sube y usa nombre con fecha y hora
    public function test_el_comando_genera_cifra_y_sube_el_backup(): void
    {
        $this->fakeProcesses();

        $this->artisan('database:backup')->assertSuccessful();

        $name = 'database_backup_2026-09-26_100000.dump';
        $this->assertFileExists($this->path($name));
        $this->assertFileExists($this->path($name.'.enc'));

        //A la nube solo sube el archivo cifrado
        $this->assertSame([$name.'.enc'], $this->remote);
        $this->assertSame('2026-09-26', File::get($this->path('last_success.txt')));
    }

    //3: usa la conexión pgsql y no expone contraseñas en los argumentos
    public function test_usa_la_conexion_pgsql_sin_exponer_credenciales(): void
    {
        $this->fakeProcesses();

        $this->artisan('database:backup')->assertSuccessful();

        $db = config('database.connections.pgsql');

        Process::assertRan(fn (PendingProcess $process) => $process->command[0] === 'pg_dump'
            && in_array('--host='.$db['host'], $process->command, true)
            && in_array('--username='.$db['username'], $process->command, true)
            && in_array('--format=custom', $process->command, true)
            && last($process->command) === $db['database']
            && $process->environment['PGPASSWORD'] === $db['password']);

        //La contraseña de la BD y la clave de cifrado nunca van como argumento visible
        $secretos = array_filter([(string) $db['password'], 'clave-de-prueba']);

        Process::assertDidntRun(fn (PendingProcess $process) => collect($secretos)
            ->contains(fn ($secreto) => str_contains(implode(' ', $process->command), $secreto)));
    }

    //4: si hay más copias que el máximo, conserva solo las 5 más recientes
    public function test_conserva_solo_las_cinco_copias_mas_recientes(): void
    {
        $viejas = $this->seedBackups(7);
        $this->fakeProcesses();

        $this->artisan('database:backup')->assertSuccessful();

        //Las 3 más antiguas se borran y quedan 4 viejas + la nueva
        foreach (array_slice($viejas, 0, 3) as $name) {
            $this->assertFileDoesNotExist($this->path($name.'.enc'));
            $this->assertNotContains($name.'.enc', $this->remote);
        }

        $this->assertCount(5, File::glob($this->path('*.dump.enc')));
        $this->assertCount(5, $this->remote);
    }

    //5: con 5 copias, la sexta borra únicamente la más antigua (local y nube)
    public function test_con_cinco_copias_la_sexta_borra_solo_la_mas_antigua(): void
    {
        $viejas = $this->seedBackups(5);
        $this->fakeProcesses();

        $this->artisan('database:backup')->assertSuccessful();

        $this->assertFileDoesNotExist($this->path($viejas[0]));
        $this->assertFileDoesNotExist($this->path($viejas[0].'.enc'));
        $this->assertNotContains($viejas[0].'.enc', $this->remote);

        foreach (array_slice($viejas, 1) as $name) {
            $this->assertFileExists($this->path($name));
            $this->assertFileExists($this->path($name.'.enc'));
            $this->assertContains($name.'.enc', $this->remote);
        }

        $this->assertCount(5, $this->remote);
    }

    //6: si pg_dump falla no se borra ninguna copia ni se intenta subir
    public function test_si_pg_dump_falla_no_se_borra_ninguna_copia(): void
    {
        $viejas = $this->seedBackups(5);
        $remotoAntes = $this->remote;
        $this->fakeProcesses(dumpOk: false);

        $this->artisan('database:backup')->assertFailed();

        $this->assertBackupsIntactos($viejas, $remotoAntes);
        $this->assertFileDoesNotExist($this->path('last_success.txt'));
        Process::assertDidntRun(fn (PendingProcess $process) => $process->command[0] === 'rclone');
    }

    //7: si la subida falla no se borra nada y el siguiente intento automático sigue pendiente
    public function test_si_la_subida_falla_no_se_borra_ninguna_copia(): void
    {
        $viejas = $this->seedBackups(5);
        $remotoAntes = $this->remote;
        File::put($this->path('last_success.txt'), '2026-09-20');
        $this->fakeProcesses(uploadOk: false);

        $this->artisan('database:backup')->assertFailed();

        $this->assertBackupsIntactos($viejas, $remotoAntes);
        Process::assertDidntRun(fn (PendingProcess $process) => ($process->command[1] ?? null) === 'deletefile');

        //La fecha del último éxito no cambia, así que se reintenta en la próxima revisión
        $this->assertSame('2026-09-20', File::get($this->path('last_success.txt')));
        $this->assertTrue(app(DatabaseBackupService::class)->isDue());
    }

    //8: la carpeta real de backups es privada, fuera de public/ y de storage/app/public
    public function test_la_carpeta_de_backups_no_es_publica(): void
    {
        $ruta = (require config_path('backup.php'))['local_path'];

        $this->assertStringStartsWith(storage_path('app'), $ruta);
        $this->assertStringStartsNotWith(public_path(), $ruta);
        $this->assertStringStartsNotWith(storage_path('app/public'), $ruta);
    }

    //9: los errores se registran en el log sin datos sensibles
    public function test_los_errores_se_registran_sin_datos_sensibles(): void
    {
        $logs = [];
        Event::listen(MessageLogged::class, function (MessageLogged $log) use (&$logs) {
            $logs[] = $log;
        });
        $this->fakeProcesses(dumpOk: false);

        $this->artisan('database:backup')->assertFailed();

        $this->assertTrue(collect($logs)->contains(fn ($log) => $log->level === 'error'
            && str_contains($log->message, 'fallo al ejecutar pg_dump')));

        $secretos = array_filter([(string) config('database.connections.pgsql.password'), 'clave-de-prueba']);

        foreach ($logs as $log) {
            $texto = $log->message.json_encode($log->context);

            foreach ($secretos as $secreto) {
                $this->assertStringNotContainsString($secreto, $texto);
            }
        }
    }

    //La limpieza nunca borra archivos que no genera este sistema
    public function test_la_limpieza_no_toca_archivos_ajenos(): void
    {
        $this->seedBackups(5);
        File::put($this->path('notas.txt'), 'no borrar');
        File::put($this->path('database_backup_manual.dump'), 'no borrar');
        $this->remote[] = 'foto.jpg';
        $this->fakeProcesses();

        $this->artisan('database:backup')->assertSuccessful();

        $this->assertFileExists($this->path('notas.txt'));
        $this->assertFileExists($this->path('database_backup_manual.dump'));
        $this->assertContains('foto.jpg', $this->remote);
    }

    //El Scheduler solo lanza el backup si pasaron los días configurados desde el último éxito
    public function test_is_due_segun_el_ultimo_backup_exitoso(): void
    {
        $service = app(DatabaseBackupService::class);
        $marca = $this->path('last_success.txt');

        $this->assertTrue($service->isDue()); //Sin backups previos

        File::put($marca, '2026-09-24');
        $this->assertFalse($service->isDue()); //Hace 2 días

        File::put($marca, '2026-09-23');
        $this->assertTrue($service->isDue()); //Hace 3 días

        File::put($marca, 'basura');
        $this->assertTrue($service->isDue()); //Archivo dañado
    }

    //Simula pg_dump, openssl y rclone; ningún proceso real llega a ejecutarse
    private function fakeProcesses(bool $dumpOk = true, bool $uploadOk = true): void
    {
        Process::fake(function (PendingProcess $process) use ($dumpOk, $uploadOk) {
            $args = $process->command;

            if ($args[0] === 'pg_dump') {
                if (! $dumpOk) {
                    return Process::result(errorOutput: 'could not connect to server', exitCode: 1);
                }

                //Crea el archivo que pg_dump habría generado
                $file = substr(collect($args)->first(fn ($arg) => str_starts_with($arg, '--file=')), 7);
                File::put($file, 'PGDMP contenido de prueba');

                return Process::result();
            }

            if ($args[0] === 'openssl') {
                File::put($args[array_search('-out', $args) + 1], 'Salted__ contenido cifrado');

                return Process::result();
            }

            if ($args[0] === 'rclone' && $args[1] === 'copyto') {
                if (! $uploadOk) {
                    return Process::result(errorOutput: 'network error', exitCode: 1);
                }

                $this->remote[] = basename($args[3]);

                return Process::result();
            }

            if ($args[0] === 'rclone' && $args[1] === 'lsf') {
                return Process::result(implode("\n", $this->remote));
            }

            if ($args[0] === 'rclone' && $args[1] === 'deletefile') {
                $this->remote = array_values(array_diff($this->remote, [basename($args[2])]));

                return Process::result();
            }

            throw new RuntimeException('Proceso no esperado: '.implode(' ', $args));
        });
    }

    //Crea N copias antiguas (días 1..N de septiembre) en local y en la nube simulada
    private function seedBackups(int $count): array
    {
        $names = [];

        foreach (range(1, $count) as $day) {
            $name = sprintf('database_backup_2026-09-%02d_020000.dump', $day);
            File::put($this->path($name), 'dump viejo');
            File::put($this->path($name.'.enc'), 'dump viejo cifrado');
            $this->remote[] = $name.'.enc';
            $names[] = $name;
        }

        return $names;
    }

    //Comprueba que las copias previas siguen intactas en local y en la nube
    private function assertBackupsIntactos(array $viejas, array $remotoAntes): void
    {
        foreach ($viejas as $name) {
            $this->assertFileExists($this->path($name));
            $this->assertFileExists($this->path($name.'.enc'));
        }

        $this->assertSame($remotoAntes, $this->remote);
    }

    //Ruta de un archivo dentro de la carpeta temporal de la prueba
    private function path(string $name): string
    {
        return $this->dir.DIRECTORY_SEPARATOR.$name;
    }
}
