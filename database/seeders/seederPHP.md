<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RendimientoSeeder extends Seeder
{
    /**
     * Ejecuta la siembra según el nivel especificado.
     * Niveles soportados: 'bajo', 'medio', 'alto'
     */
    public function run(string $nivel = 'bajo'): void
    {
        $configuracion = match ($nivel) {
            'medio' => [
                'categorias'        => 35,
                'marcas'            => 35,
                'proveedores'       => 100,
                'productos'         => 3000,
                'compras'           => 2000,
                'lotes'             => 5000,
                'ventas'            => 5000,
                'creditos'          => 600,
                'abonos'            => 3000,
                'productos_daniados'=> 500,
                'devoluciones'      => 250,
                'cambios'           => 250,
                'notas'             => 600,
            ],
            'alto' => [
                'categorias'        => 50,
                'marcas'            => 50,
                'proveedores'       => 200,
                'productos'         => 5000,
                'compras'           => 3000,
                'lotes'             => 10000,
                'ventas'            => 10000,
                'creditos'          => 1000,
                'abonos'            => 5000,
                'productos_daniados'=> 1000,
                'devoluciones'      => 500,
                'cambios'           => 500,
                'notas'             => 1000,
            ],
            default => [ // bajo
                'categorias'        => 20,
                'marcas'            => 20,
                'proveedores'       => 50,
                'productos'         => 1000,
                'compras'           => 1000,
                'lotes'             => 2000,
                'ventas'            => 2000,
                'creditos'          => 300,
                'abonos'            => 1000,
                'productos_daniados'=> 200,
                'devoluciones'      => 100,
                'cambios'           => 100,
                'notas'             => 300,
            ],
        };

        // 0. Dependencias base (Roles, Usuario Admin, Config, Métodos de Pago)
        $this->call([
            RoleSeeder::class,
            UserSeeder::class,
            ConfiguracionSeeder::class,
            MetodoPagoSeeder::class,
        ]);

        $adminId = DB::table('users')->first()?->id ?? 1;
        $metodosPago = DB::table('metodos_pagos')->pluck('id', 'nombre')->toArray();
        $efectivoId = $metodosPago['EFECTIVO'] ?? 1;

        $secciones = ['TIENDA', 'LIBRERIA', 'MEDICAMENTO'];
        $now = Carbon::now()->toDateTimeString();

        // 1. Categorías y Marcas
        $categoriasIds = [];
        $marcasIds = [];

        foreach ($secciones as $seccion) {
            $catCount = (int) ceil($configuracion['categorias'] / count($secciones));
            for ($i = 1; $i <= $catCount; $i++) {
                $categoriasIds[] = DB::table('categorias')->insertGetId([
                    'nombre'     => "Categoría {$seccion} {$i}",
                    'seccion'    => $seccion,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $marcaCount = (int) ceil($configuracion['marcas'] / count($secciones));
            for ($i = 1; $i <= $marcaCount; $i++) {
                $marcasIds[] = DB::table('marcas')->insertGetId([
                    'nombre'     => "Marca {$seccion} {$i}",
                    'seccion'    => $seccion,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        // 2. Proveedores y Clientes de Crédito
        $proveedoresData = [];
        for ($i = 1; $i <= $configuracion['proveedores']; $i++) {
            $proveedoresData[] = [
                'nombre'     => "Proveedor Pruebas {$i}",
                'telefono'   => '7000' . str_pad($i, 4, '0', STR_PAD_LEFT),
                'email'      => "proveedor{$i}@ejemplo.com",
                'direccion'  => "Dirección Proveedor {$i}",
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('proveedores')->insert($proveedoresData);
        $proveedoresIds = DB::table('proveedores')->pluck('id')->toArray();

        $clientesData = [];
        $cantClientes = max(50, (int) ceil($configuracion['creditos'] / 2));
        for ($i = 1; $i <= $cantClientes; $i++) {
            $clientesData[] = [
                'nombre'     => "Cliente Crédito {$i}",
                'dui'        => str_pad($i, 8, '0', STR_PAD_LEFT) . '-9',
                'telefono'   => '7100' . str_pad($i, 4, '0', STR_PAD_LEFT),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('clientes_creditos')->insert($clientesData);
        $clientesIds = DB::table('clientes_creditos')->pluck('id')->toArray();

        // 3. Productos
        $productosBatch = [];
        for ($i = 1; $i <= $configuracion['productos']; $i++) {
            $sec = $secciones[$i % count($secciones)];
            $perecedero = ($sec === 'MEDICAMENTO' || $i % 3 === 0) ? 'PERECEDERO' : 'NORMAL';
            $precioDet = round(rand(2, 100) + (rand(0, 99) / 100), 2);
            $precioMay = round($precioDet * 0.85, 2);

            $productosBatch[] = [
                'nombre'          => "Producto Test {$i}",
                'precio_detalle'  => $precioDet,
                'precio_mayor'    => $precioMay,
                'costo_promedio'  => round($precioDet * 0.60, 2),
                'stock'           => rand(20, 500),
                'stock_minimo'    => 10,
                'seccion'         => $sec,
                'perecedero'      => $perecedero,
                'estado'          => 'ACTIVO',
                'marca_id'        => $marcasIds[array_rand($marcasIds)],
                'categoria_id'    => $categoriasIds[array_rand($categoriasIds)],
                'created_at'      => $now,
                'updated_at'      => $now,
            ];

            if (count($productosBatch) >= 1000) {
                DB::table('productos')->insert($productosBatch);
                $productosBatch = [];
            }
        }
        if (!empty($productosBatch)) {
            DB::table('productos')->insert($productosBatch);
        }

        $productos = DB::table('productos')->select('id', 'perecedero', 'costo_promedio', 'precio_detalle', 'precio_mayor')->get();
        $prodPerecederosIds = $productos->where('perecedero', 'PERECEDERO')->pluck('id')->toArray();

        // 4. Compras y Lotes
        $lotesPorCompra = max(1, (int) ceil($configuracion['lotes'] / $configuracion['compras']));
        $lotesInsertados = 0;

        for ($c = 1; $c <= $configuracion['compras']; $c++) {
            $fechaCompraDate = Carbon::now()->subDays(rand(1, 180))->toDateString();
            $fechaCompraNow = $fechaCompraDate . ' 10:00:00';

            $compraId = DB::table('compras')->insertGetId([
                'numero_factura' => 'FAC-' . str_pad($c, 6, '0', STR_PAD_LEFT),
                'codigo_factura' => 'COD-' . str_pad($c, 6, '0', STR_PAD_LEFT),
                'fecha_registro' => $fechaCompraDate,
                'fecha_emision'  => $fechaCompraDate,
                'total'          => 500.00,
                'estado'         => 'REGISTRADA',
                'user_id'        => $adminId,
                'proveedor_id'   => $proveedoresIds[array_rand($proveedoresIds)],
                'created_at'     => $fechaCompraNow,
                'updated_at'     => $fechaCompraNow,
            ]);

            $prodId = $prodPerecederosIds[array_rand($prodPerecederosIds)] ?? $productos[0]->id;

            DB::table('detalle_compras')->insert([
                'compra_id'         => $compraId,
                'producto_id'       => $prodId,
                'cantidad'          => 50,
                'precio_unitario'   => 10.00,
                'factor_conversion' => 1,
                'margen_detalle'    => 30.00,
                'margen_mayor'      => 15.00,
                'subtotal'          => 500.00,
                'created_at'        => $fechaCompraNow,
                'updated_at'        => $fechaCompraNow,
            ]);

            if ($lotesInsertados < $configuracion['lotes']) {
                for ($l = 0; $l < $lotesPorCompra && $lotesInsertados < $configuracion['lotes']; $l++) {
                    $lotesInsertados++;
                    DB::table('lotes')->insert([
                        'codigo_lote'       => 'LOT-' . str_pad($lotesInsertados, 6, '0', STR_PAD_LEFT),
                        'fecha_ingreso'     => $fechaCompraDate,
                        'fecha_vencimiento' => Carbon::now()->addDays(rand(30, 365))->toDateString(),
                        'cantidad_inicial'  => 100,
                        'cantidad_actual'   => 80,
                        'estado'            => 'ACTIVO',
                        'producto_id'       => $prodId,
                        'compra_id'         => $compraId,
                        'created_at'        => $fechaCompraNow,
                        'updated_at'        => $fechaCompraNow,
                    ]);
                }
            }
        }

        $lotesIds = DB::table('lotes')->pluck('id')->toArray();

        // 5. Ventas y Detalle de Ventas
        $ventasAbono = [];
        $ventasDevolucion = [];

        for ($v = 1; $v <= $configuracion['ventas']; $v++) {
            $esHoy = ($v <= 100 || $v % 10 === 0);
            $fechaVentaTime = $esHoy
                ? Carbon::now()->subHours(rand(1, 10))->toDateTimeString()
                : Carbon::now()->subDays(rand(1, 180))->subHours(rand(1, 10))->toDateTimeString();

            $esCredito = ($v <= $configuracion['creditos']);
            $estado = $esCredito ? 'CREDITO' : 'PAGADA';
            $metodoId = $esCredito ? null : ($v % 2 === 0 ? ($metodosPago['EFECTIVO'] ?? 1) : ($metodosPago['TRANSFERENCIA'] ?? 2));

            $ventaId = DB::table('ventas')->insertGetId([
                'correlativo'   => $v,
                'fecha'         => $fechaVentaTime,
                'total'         => 45.00,
                'monto_recibido'=> 50.00,
                'tipo_cliente'  => ($v % 3 === 0) ? 'MAYORISTA' : 'DETALLES',
                'estado'        => $estado,
                'user_id'       => $adminId,
                'metodo_pago_id'=> $metodoId,
                'created_at'    => $fechaVentaTime,
                'updated_at'    => $fechaVentaTime,
            ]);

            $prod = $productos->random();
            $detId = DB::table('detalle_ventas')->insertGetId([
                'venta_id'       => $ventaId,
                'producto_id'    => $prod->id,
                'cantidad'       => 3,
                'precio_unitario'=> 15.00,
                'subtotal'       => 45.00,
                'created_at'     => $fechaVentaTime,
                'updated_at'     => $fechaVentaTime,
            ]);

            if ($esCredito) {
                $clienteId = $clientesIds[array_rand($clientesIds)];
                $creditoId = DB::table('creditos')->insertGetId([
                    'venta_id'           => $ventaId,
                    'cliente_credito_id' => $clienteId,
                    'monto_adeudado'     => 45.00,
                    'saldo'              => 20.00,
                    'estado'             => 'PENDIENTE',
                    'created_at'         => $fechaVentaTime,
                    'updated_at'         => $fechaVentaTime,
                ]);
                $ventasAbono[] = ['credito_id' => $creditoId, 'fecha' => $fechaVentaTime];
            }

            if (count($ventasDevolucion) < $configuracion['devoluciones'] && $v % 4 === 0) {
                $ventasDevolucion[] = [
                    'venta_id'    => $ventaId,
                    'detalle_id'  => $detId,
                    'producto_id' => $prod->id,
                    'fecha'       => Carbon::parse($fechaVentaTime)->toDateString()
                ];
            }
        }

        // 6. Abonos
        $abonosBatch = [];
        $totalAbonos = $configuracion['abonos'];
        for ($a = 1; $a <= $totalAbonos; $a++) {
            $item = $ventasAbono[array_rand($ventasAbono)];
            $abonosBatch[] = [
                'credito_id'     => $item['credito_id'],
                'monto'          => 5.00,
                'fecha_abono'    => Carbon::parse($item['fecha'])->toDateString(),
                'estado'         => 'PAGADO',
                'metodo_pago_id' => $efectivoId,
                'created_at'     => $item['fecha'],
                'updated_at'     => $item['fecha'],
            ];

            if (count($abonosBatch) >= 1000) {
                DB::table('abonos')->insert($abonosBatch);
                $abonosBatch = [];
            }
        }
        if (!empty($abonosBatch)) {
            DB::table('abonos')->insert($abonosBatch);
        }

        // 7. Productos Dañados
        $daniadosBatch = [];
        $origenes = ['VENTA', 'DIRECTO', 'VENCIMIENTO', 'PROVEEDOR'];
        for ($d = 1; $d <= $configuracion['productos_daniados']; $d++) {
            $prod = $productos->random();
            $loteId = !empty($lotesIds) ? $lotesIds[array_rand($lotesIds)] : null;
            $fechaDanDate = Carbon::now()->subDays(rand(1, 150))->toDateString();
            $fechaDanTime = $fechaDanDate . ' 12:00:00';

            $daniadosBatch[] = [
                'producto_id'    => $prod->id,
                'lote_id'        => $loteId,
                'cantidad'       => rand(1, 5),
                'costo_unitario' => $prod->costo_promedio ?? 5.00,
                'total_perdida'  => round(rand(1, 5) * ($prod->costo_promedio ?? 5.00), 2),
                'descripcion'    => "Producto dañado en prueba {$d}",
                'fecha'          => $fechaDanDate,
                'origen'         => $origenes[array_rand($origenes)],
                'estado'         => 'REGISTRADO',
                'created_at'     => $fechaDanTime,
                'updated_at'     => $fechaDanTime,
            ];

            if (count($daniadosBatch) >= 1000) {
                DB::table('productos_daniados')->insert($daniadosBatch);
                $daniadosBatch = [];
            }
        }
        if (!empty($daniadosBatch)) {
            DB::table('productos_daniados')->insert($daniadosBatch);
        }

        // 8. Devoluciones de Ventas
        foreach ($ventasDevolucion as $dev) {
            $devId = DB::table('devoluciones_ventas')->insertGetId([
                'venta_id'   => $dev['venta_id'],
                'fecha'      => $dev['fecha'],
                'total'      => 15.00,
                'motivo'     => 'Devolución de prueba por cliente',
                'estado'     => 'DEVUELTA',
                'created_at' => $dev['fecha'] . ' 11:00:00',
                'updated_at' => $dev['fecha'] . ' 11:00:00',
            ]);

            DB::table('detalle_devoluciones_ventas')->insert([
                'devolucion_venta_id' => $devId,
                'producto_id'         => $dev['producto_id'],
                'detalle_venta_id'    => $dev['detalle_id'],
                'cantidad'            => 1,
                'precio_unitario'     => 15.00,
                'subtotal'            => 15.00,
                'condicion'           => (rand(0, 1) === 1) ? 'PERFECTO' : 'DANIADO',
                'created_at'          => $dev['fecha'] . ' 11:00:00',
                'updated_at'          => $dev['fecha'] . ' 11:00:00',
            ]);
        }

        // 9. Cambios de Producto
        $cambiosBatch = [];
        $estadosCambio = ['PENDIENTE', 'ACEPTADO', 'RECHAZADO', 'ANULADO'];
        for ($c = 1; $c <= $configuracion['cambios']; $c++) {
            $prodOriginal = $productos->random();
            $prodReemplazo = $productos->random();
            $fechaCambioDate = Carbon::now()->subDays(rand(1, 150))->toDateString();
            $fechaCambioTime = $fechaCambioDate . ' 14:00:00';

            $cambiosBatch[] = [
                'fecha'                  => $fechaCambioDate,
                'producto_id'            => $prodOriginal->id,
                'producto_reemplazo_id'  => $prodReemplazo->id,
                'lote_id'                => !empty($lotesIds) ? $lotesIds[array_rand($lotesIds)] : null,
                'cantidad'               => rand(1, 3),
                'costo_unitario'         => $prodOriginal->costo_promedio ?? 5.00,
                'total_perdida'          => round(rand(1, 3) * ($prodOriginal->costo_promedio ?? 5.00), 2),
                'descripcion'            => "Solicitud de cambio de prueba {$c}",
                'estado'                 => $estadosCambio[array_rand($estadosCambio)],
                'origen'                 => 'MANUAL',
                'created_at'             => $fechaCambioTime,
                'updated_at'             => $fechaCambioTime,
            ];

            if (count($cambiosBatch) >= 1000) {
                DB::table('cambios_productos')->insert($cambiosBatch);
                $cambiosBatch = [];
            }
        }
        if (!empty($cambiosBatch)) {
            DB::table('cambios_productos')->insert($cambiosBatch);
        }

        // 10. Notas
        $notasBatch = [];
        for ($n = 1; $n <= $configuracion['notas']; $n++) {
            $fechaNota = Carbon::now()->subDays(rand(1, 90))->toDateString();
            $notasBatch[] = [
                'fecha'      => $fechaNota,
                'contenido'  => "Contenido de la nota {$n} para prueba de rendimiento.",
                'user_id'    => $adminId,
                'created_at' => $fechaNota . ' 09:00:00',
                'updated_at' => $fechaNota . ' 09:00:00',
            ];

            if (count($notasBatch) >= 1000) {
                DB::table('notas')->insert($notasBatch);
                $notasBatch = [];
            }
        }
        if (!empty($notasBatch)) {
            DB::table('notas')->insert($notasBatch);
        }
    }
}
