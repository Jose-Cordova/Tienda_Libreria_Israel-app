<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::where('name', 'ADMIN')
                        ->where('guard_name', 'api')
                        ->firstOrFail();

        $admin = User::create([
            'name'     => 'Tony',
            'email'    => 'devtony@gmail.com',
            'password' => bcrypt('12345678'),
            'estado'   => 'ACTIVO',
        ]);

        $admin->assignRole($adminRole);
    }
}
