<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Configuracion;

class ConfiguracionSeeder extends Seeder
{
    public function run(): void
    {
        Configuracion::firstOrCreate([], [
            'nombre_tienda' => 'Tienda y Librería Israel',
            'telefono'      => '2233-4455',
            'email'         => 'tienda@israel.com',
        ]);
    }
}
