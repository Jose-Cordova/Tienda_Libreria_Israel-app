<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RolesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('roles')->insert([
            ['nombre' => 'admin',    'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'vendedor', 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'cajero',   'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'bodega',   'created_at' => now(), 'updated_at' => now()], //
        ]);
}
}
