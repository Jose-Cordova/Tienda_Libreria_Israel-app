<?php

namespace Database\Seeders;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Ejecutar seeders externos primero (ej. Roles y Permisos)
        $this->call([
            RoleSeeder::class,
            // OtrosSeeders::class,
        ]);

        // 2. Crear registros específicos o masivos
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // Opcional: Crear más usuarios aleatorios
        // User::factory(10)->create();
    }
}
