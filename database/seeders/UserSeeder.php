<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Buscar el rol de administrador para la API
        $adminRole = Role::where('name', 'ADMIN')
                        ->where('guard_name', 'api')
                        ->firstOrFail();

        // Crear o buscar el usuario administrador master
        $admin = User::firstOrCreate(
            ['email' => 'adminmaster@gmail.com'],
            [
                'name'     => 'ADMINMASTER',
                'password' => bcrypt('adminmaster123'),
                'estado'   => 'ACTIVO',
            ]
        );

        // Asignar y sincronizar el rol de administrador
        $admin->syncRoles([$adminRole]);
    }
}
