<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Services\Backup\DatabaseBackupService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

//Se revisa cada hora con la PC encendida y corre si el último backup exitoso tiene la antigüedad configurada
Schedule::command('database:backup')
    ->hourly()
    ->when(fn () => app(DatabaseBackupService::class)->isDue())
    ->withoutOverlapping();
