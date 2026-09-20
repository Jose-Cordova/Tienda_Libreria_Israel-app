<?php

    namespace App\Console\Commands;

    use Illuminate\Console\Command;
    use App\Models\Lote;
    use App\Models\CambioProducto;
    use Illuminate\Support\Facades\DB;

    class ProcesarLotesVencidos extends Command
    {
        protected $signature = 'inventario:procesar-vencidos';
        protected $description = 'Traslada automáticamente los lotes vencidos al módulo de Cambio de Producto';

        public function handle()
        {
            $this->info('Iniciando procesamiento de lotes vencidos...');

            $hoy = now()->toDateString();

            $lotesVencidos = Lote::where('estado', 'ACTIVO')
                ->where('cantidad_actual', '>', 0)
                ->where('fecha_vencimiento', '<=', $hoy)
                ->get();

            $procesados = 0;

            foreach ($lotesVencidos as $lote) {
                DB::transaction(function () use ($lote, &$procesados) {
                    $producto = $lote->producto;
                    $cantidad = $lote->cantidad_actual;

                    // 1. Inactivar el lote
                    $lote->estado = 'INACTIVO';
                    $lote->motivo_inactivo = 'VENCIMIENTO';
                    $lote->cantidad_actual = 0;
                    $lote->save();

                    // 2. Descontar el stock general del producto
                    $producto->decrement('stock', $cantidad);
                    if ($producto->stock <= 0) {
                        $producto->update(['estado' => 'INACTIVO']);
                    }

                    // 3. Obtener costo
                    $costoUnitario = $producto->costo_promedio ?: 0.00;
                    if ($costoUnitario <= 0 && $producto->ultimoDetalleCompra) {
                        $costoUnitario = $producto->ultimoDetalleCompra->precio_unitario;
                    }

                    // 4. Registrar en CambioProducto
                    CambioProducto::create([
                        'producto_id'    => $producto->id,
                        'lote_id'        => $lote->id,
                        'cantidad'       => $cantidad,
                        'descripcion'    => "Vencimiento automático de lote ({$lote->codigo_lote})",
                        'fecha'          => now(),
                        'costo_unitario' => $costoUnitario,
                        'total_perdida'  => $costoUnitario * $cantidad,
                        'estado'         => 'PENDIENTE',
                        'origen'         => 'VENCIMIENTO',
                    ]);

                    $procesados++;
                });
            }

            $this->info("Proceso finalizado. Total de lotes procesados por vencimiento: {$procesados}");
        }
    }
