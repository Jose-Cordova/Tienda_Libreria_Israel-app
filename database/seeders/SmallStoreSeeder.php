<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use App\Models\User;
use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\Lote;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SmallStoreSeeder extends Seeder
{
    public function run()
    {
        // 1. Create Roles
        Role::firstOrCreate(['name' => 'VENDEDOR', 'guard_name' => 'api']);
        $adminRole = Role::firstOrCreate(['name' => 'ADMIN', 'guard_name' => 'api']);

        // 2. Create User
        $user = User::firstOrCreate(
            ['email' => 'adonayxddddddd@gmail.com'],
            [
                'name' => 'Adonay Antonio Zamora Navas',
                'password' => Hash::make('12345678'),
                'estado' => 'ACTIVO'
            ]
        );
        $user->assignRole($adminRole);

        $categorias = [
            ['nombre' => 'Antibióticos', 'sesion' => 'MEDICAMENTO'],
            ['nombre' => 'Analgésicos', 'sesion' => 'MEDICAMENTO'],
            ['nombre' => 'Vitaminas', 'sesion' => 'MEDICAMENTO'],
            ['nombre' => 'Jeringas', 'sesion' => 'MEDICAMENTO'],
            ['nombre' => 'Cuadernos Escolares', 'sesion' => 'LIBRERIA'],
            ['nombre' => 'Libros de Texto', 'sesion' => 'LIBRERIA'],
            ['nombre' => 'Útiles de Oficina', 'sesion' => 'LIBRERIA'],
            ['nombre' => 'Bebidas Carbonatadas', 'sesion' => 'DESPENSA'],
            ['nombre' => 'Galletas Dulces', 'sesion' => 'DESPENSA'],
            ['nombre' => 'Lácteos', 'sesion' => 'DESPENSA'],
        ];
        
        $catIds = [];
        foreach ($categorias as $catData) {
            $cat = Categoria::firstOrCreate(['nombre' => $catData['nombre']], ['sesion' => $catData['sesion']]);
            $catIds[$catData['nombre']] = $cat->id;
        }

        $marcas = [
            ['nombre' => 'Bayer', 'sesion' => 'MEDICAMENTO'],
            ['nombre' => 'Pfizer', 'sesion' => 'MEDICAMENTO'],
            ['nombre' => 'MK', 'sesion' => 'MEDICAMENTO'],
            ['nombre' => 'Scribe', 'sesion' => 'LIBRERIA'],
            ['nombre' => 'Santillana', 'sesion' => 'LIBRERIA'],
            ['nombre' => '3M', 'sesion' => 'LIBRERIA'],
            ['nombre' => 'Coca Cola', 'sesion' => 'DESPENSA'],
            ['nombre' => 'Pozuelo', 'sesion' => 'DESPENSA'],
            ['nombre' => 'Dos Pinos', 'sesion' => 'DESPENSA'],
            ['nombre' => 'Norma', 'sesion' => 'LIBRERIA'],
        ];

        $marcaIds = [];
        foreach ($marcas as $marcaData) {
            $marca = Marca::firstOrCreate(['nombre' => $marcaData['nombre']], ['sesion' => $marcaData['sesion']]);
            $marcaIds[$marcaData['nombre']] = $marca->id;
        }

        // 5. Productos (10)
        $productos = [
            ['nombre' => 'Aspirina 500mg', 'precio_detalle' => 0.25, 'precio_mayor' => 0.20, 'cantidad_inicial' => 100, 'stock_minimo' => 20, 'sesion' => 'MEDICAMENTO', 'perecedero' => 'PERECEDERO', 'estado' => 'ACTIVO', 'marca_id' => $marcaIds['Bayer'], 'categoria_id' => $catIds['Analgésicos']],
            ['nombre' => 'Amoxicilina 500mg', 'precio_detalle' => 0.50, 'precio_mayor' => 0.40, 'cantidad_inicial' => 50, 'stock_minimo' => 10, 'sesion' => 'MEDICAMENTO', 'perecedero' => 'PERECEDERO', 'estado' => 'ACTIVO', 'marca_id' => $marcaIds['Pfizer'], 'categoria_id' => $catIds['Antibióticos']],
            ['nombre' => 'Vitamina C', 'precio_detalle' => 3.00, 'precio_mayor' => 2.50, 'cantidad_inicial' => 30, 'stock_minimo' => 5, 'sesion' => 'MEDICAMENTO', 'perecedero' => 'PERECEDERO', 'estado' => 'ACTIVO', 'marca_id' => $marcaIds['MK'], 'categoria_id' => $catIds['Vitaminas']],
            ['nombre' => 'Jeringa 5ml', 'precio_detalle' => 0.15, 'precio_mayor' => 0.10, 'cantidad_inicial' => 200, 'stock_minimo' => 50, 'sesion' => 'MEDICAMENTO', 'perecedero' => 'NORMAL', 'estado' => 'ACTIVO', 'marca_id' => $marcaIds['MK'], 'categoria_id' => $catIds['Jeringas']],
            
            ['nombre' => 'Cuaderno 200 Hojas', 'precio_detalle' => 1.50, 'precio_mayor' => 1.25, 'cantidad_inicial' => 60, 'stock_minimo' => 10, 'sesion' => 'LIBRERIA', 'perecedero' => 'NORMAL', 'estado' => 'ACTIVO', 'marca_id' => $marcaIds['Scribe'], 'categoria_id' => $catIds['Cuadernos Escolares']],
            ['nombre' => 'Libro Matemáticas 1', 'precio_detalle' => 15.00, 'precio_mayor' => 12.00, 'cantidad_inicial' => 15, 'stock_minimo' => 3, 'sesion' => 'LIBRERIA', 'perecedero' => 'NORMAL', 'estado' => 'ACTIVO', 'marca_id' => $marcaIds['Santillana'], 'categoria_id' => $catIds['Libros de Texto']],
            ['nombre' => 'Notas Adhesivas', 'precio_detalle' => 2.00, 'precio_mayor' => 1.50, 'cantidad_inicial' => 40, 'stock_minimo' => 8, 'sesion' => 'LIBRERIA', 'perecedero' => 'NORMAL', 'estado' => 'ACTIVO', 'marca_id' => $marcaIds['3M'], 'categoria_id' => $catIds['Útiles de Oficina']],
            
            ['nombre' => 'Coca Cola 3L', 'precio_detalle' => 2.50, 'precio_mayor' => 2.25, 'cantidad_inicial' => 80, 'stock_minimo' => 15, 'sesion' => 'DESPENSA', 'perecedero' => 'PERECEDERO', 'estado' => 'ACTIVO', 'marca_id' => $marcaIds['Coca Cola'], 'categoria_id' => $catIds['Bebidas Carbonatadas']],
            ['nombre' => 'Galletas Chiky', 'precio_detalle' => 1.00, 'precio_mayor' => 0.85, 'cantidad_inicial' => 100, 'stock_minimo' => 20, 'sesion' => 'DESPENSA', 'perecedero' => 'NORMAL', 'estado' => 'ACTIVO', 'marca_id' => $marcaIds['Pozuelo'], 'categoria_id' => $catIds['Galletas Dulces']],
            ['nombre' => 'Leche Semidescremada', 'precio_detalle' => 1.80, 'precio_mayor' => 1.60, 'cantidad_inicial' => 40, 'stock_minimo' => 10, 'sesion' => 'DESPENSA', 'perecedero' => 'PERECEDERO', 'estado' => 'ACTIVO', 'marca_id' => $marcaIds['Dos Pinos'], 'categoria_id' => $catIds['Lácteos']]
        ];

        foreach ($productos as $p) {
            $cantidad_inicial = $p['cantidad_inicial'];
            unset($p['cantidad_inicial']);
            $p['stock'] = $cantidad_inicial;

            $producto = Producto::firstOrCreate(
                ['nombre' => $p['nombre']],
                $p
            );

            // Si es perecedero y no existe un lote, se crea uno
            if ($producto->wasRecentlyCreated && $producto->perecedero === 'PERECEDERO') {
                Lote::create([
                    'fecha_vencimiento' => now()->addMonths(6)->format('Y-m-d'), // Lote vence en 6 meses
                    'codigo_lote'       => 'LOTE-' . strtoupper(Str::random(5)),
                    'fecha_ingreso'     => now(),
                    'cantidad_inicial'  => $cantidad_inicial,
                    'cantidad_actual'   => $cantidad_inicial,
                    'estado'            => 'ACTIVO',
                    'producto_id'       => $producto->id
                ]);
            }
        }
    }
}
