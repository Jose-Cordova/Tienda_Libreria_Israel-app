<?php

namespace Database\Seeders;

use App\Models\MetodoPago;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;

class MetodoPagoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        MetodoPago::firstOrCreate(['nombre' => 'EFECTIVO']);
        MetodoPago::firstOrCreate(['nombre' => 'TRANSFERENCIA']);
    }
}
