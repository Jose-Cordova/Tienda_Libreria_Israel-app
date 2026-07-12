<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Venta;
use App\Models\DevolucionVenta;
use App\Models\DetalleVenta;
use App\Models\DetalleDevolucionVenta;
use App\Models\ProductoDaniado;
use Illuminate\Support\Facades\DB;
use Exception;
//Requests
use App\Http\Requests\UpdateDevolucionVentaRequest;
use App\Http\Requests\StoreDevolucionVentaRequest;

class DevolucionVentaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
{
    try {
        $request->validate([
            'per_page'     => 'nullable|integer|min:1|max:100',
            'estado'       => 'nullable|in:DEVUELTA,ANULADA',
            'fecha_inicio' => 'nullable|date',
            'fecha_fin'    => 'nullable|date|after_or_equal:fecha_inicio',
        ]);

        $query = DevolucionVenta::with([
            'detalleDevolucionVentas.producto',
            'detalleDevolucionVentas.productoDaniado',
            'detalleDevolucionVentas.detalleVenta.lote',
            'venta'
        ]);

        // Filtro por estado
        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        // Filtro por fecha de inicio
        if ($request->fecha_inicio) {
            $query->whereDate('fecha', '>=', $request->fecha_inicio);
        }
        if ($request->fecha_fin) {
            $query->whereDate('fecha', '<=', $request->fecha_fin);
        }

        // Filtro por venta (ya existente)
        if ($request->filled('venta_id')) {
            $query->where('venta_id', $request->venta_id);
        }

        $perPage = $request->get('per_page', 15);
        $devoluciones = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json($devoluciones, 200);

    } catch (Exception $e) {
        return response()->json([
            'message' => 'Error al obtener las devoluciones.',
            'error'   => $e->getMessage()
        ], 500);
    }
}

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDevolucionVentaRequest $request)
{
    $data = $request->validated();

    try {
        DB::beginTransaction();

        // 1. Verificar que la venta exista y esté en estado PAGADA o CREDITO
        $venta = Venta::with('detalleVentas.lote', 'credito')->findOrFail($data['venta_id']);

        if (!in_array($venta->estado, ['PAGADA', 'CREDITO'])) {
            return response()->json([
                'message' => 'Solo se pueden devolver ventas pagadas o a crédito.'
            ], 400);
        }

        // ✅ Nueva validación: no permitir si el crédito tiene abonos
        if ($venta->credito && $venta->credito->saldo != 0) {
            return response()->json([
                'message' => 'No se puede devolver una venta a crédito que ya tiene abonos registrados.'
            ], 400);
        }


        // 2. Crear la devolución
        $devolucion = DevolucionVenta::create([
            'fecha'    => now(),
            'motivo'   => $data['motivo'],
            'total'    => 0,
            'venta_id' => $venta->id,
        ]);

        $totalDevolucion = 0;

        // 3. Procesar cada detalle de la devolución
        foreach ($data['detalle'] as $item) {
            $detalleVenta = DetalleVenta::findOrFail($item['detalle_venta_id']);
            $producto = $detalleVenta->producto;
            $cantidad = $item['cantidad'];
            $precioUnitario = $detalleVenta->precio_unitario;
            $subtotal = $precioUnitario * $cantidad;

            // Crear el detalle de devolución
            $detalleDevolucion = DetalleDevolucionVenta::create([
                'cantidad'            => $cantidad,
                'precio_unitario'     => $precioUnitario,
                'subtotal'            => $subtotal,
                'condicion'           => $item['condicion'],
                'devolucion_venta_id' => $devolucion->id,
                'producto_id'         => $detalleVenta->producto_id,
                'detalle_venta_id'    => $detalleVenta->id,
                'producto_daniado_id' => null,
            ]);

            // 4. Lógica según condición
            if ($item['condicion'] === 'PERFECTO') {
                // Incrementar stock del producto
                $producto->increment('stock', $cantidad);

                // Si la venta original usó un lote, devolver la cantidad a ese lote
                if ($detalleVenta->lote_id && $detalleVenta->lote) {
                    $lote = $detalleVenta->lote;
                    $lote->cantidad_actual += $cantidad;
                    if ($lote->estado === 'INACTIVO' && $lote->cantidad_actual > 0) {
                        $lote->estado = 'ACTIVO';
                        $lote->motivo_inactivo = null;
                    }
                    $lote->save();
                }
            } elseif ($item['condicion'] === 'DANIADO') {
                // Crear registro en ProductoDaniado
                $productoDaniado = ProductoDaniado::create([
                    'descripcion'    => $item['descripcion'],
                    'cantidad'       => $cantidad,
                    'fecha'          => now(),
                    'costo_unitario' => $precioUnitario,
                    'total_perdida'  => $subtotal,
                    'producto_id'    => $detalleVenta->producto_id,
                ]);

                // Asignar el ID al detalle de devolución
                $detalleDevolucion->update([
                    'producto_daniado_id' => $productoDaniado->id
                ]);
            }

            $totalDevolucion += $subtotal;
        }

        // 5. Actualizar el total de la devolución
        $devolucion->update(['total' => $totalDevolucion]);

        // 6. Cambiar el estado de la venta a DEVOLUCION automáticamente
        $venta->update(['estado' => 'DEVOLUCION']);

        // 7. Si la venta era a crédito, ajustar el saldo
        if ($venta->credito) {
            $credito = $venta->credito;
            $credito->monto_adeudado -= $totalDevolucion;

            if ($credito->monto_adeudado <= 0) {
                $credito->monto_adeudado = 0;
                $credito->estado = 'PAGADO';
                $credito->fecha_cancelada = now();
            }
            $credito->save();
        }

        DB::commit();

        // Cargar relaciones para la respuesta
        $devolucion->load([
            'detalleDevolucionVentas.producto',
            'detalleDevolucionVentas.detalleVenta.lote',
            'detalleDevolucionVentas.productoDaniado',
            'venta'
        ]);

        return response()->json([
            'message'     => 'Devolución registrada correctamente.',
            'devolucion'  => $devolucion
        ], 201);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'message' => 'Error al registrar la devolución.',
            'error'   => $e->getMessage()
        ], 500);
    }
}

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
        $devolucion = DevolucionVenta::with([
            'detalleDevolucionVentas.producto',
            'detalleDevolucionVentas.detalleVenta.lote',
            'detalleDevolucionVentas.productoDaniado',
            'venta.user',
            'venta.metodoPago',
        ])->findOrFail($id);

        return response()->json([
            'devolucion' => $devolucion
        ], 200);

    } catch (ModelNotFoundException $e) {
        return response()->json([
            'message' => 'Devolución no encontrada.'
        ], 404);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Error al obtener la devolución.',
            'error'   => $e->getMessage()
        ], 500);
    }
    }
    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
        DB::beginTransaction();

        $devolucion = DevolucionVenta::with([
            'detalleDevolucionVentas.producto',
            'detalleDevolucionVentas.detalleVenta.lote',
            'detalleDevolucionVentas.productoDaniado',
        ])->findOrFail($id);

        // 1. Solo se puede anular si está DEVUELTA
        if ($devolucion->estado !== 'DEVUELTA') {
            return response()->json([
                'message' => 'Solo se pueden anular devoluciones en estado DEVUELTA.'
            ], 400);
        }

        $venta = $devolucion->venta;
        $totalDevolucion = $devolucion->total;

        // 2. Revertir cada detalle
        foreach ($devolucion->detalleDevolucionVentas as $detalle) {
            $producto = $detalle->producto;
            $cantidad = $detalle->cantidad;

            if ($detalle->condicion === 'PERFECTO') {
                // Decrementar stock del producto
                if ($producto) {
                    $producto->decrement('stock', $cantidad);
                }

                // Decrementar lote si aplica
                if ($detalle->detalleVenta && $detalle->detalleVenta->lote_id && $detalle->detalleVenta->lote) {
                    $lote = $detalle->detalleVenta->lote;
                    $lote->cantidad_actual -= $cantidad;
                    if ($lote->cantidad_actual <= 0) {
                        $lote->estado = 'INACTIVO';
                        $lote->motivo_inactivo = 'AGOTADO';
                    }
                    $lote->save();
                }
            } elseif ($detalle->condicion === 'DANIADO') {
                // Eliminar el producto dañado asociado
                if ($detalle->productoDaniado) {
                    $detalle->productoDaniado->delete();
                }
            }
        }

        // 3. Revertir ajuste de crédito si la venta era a crédito
        if ($venta->credito) {
            $credito = $venta->credito;
            $credito->monto_adeudado += $totalDevolucion;

            if ($credito->estado === 'PAGADO' && $credito->monto_adeudado > 0) {
                $credito->estado = 'PENDIENTE';
                $credito->fecha_cancelada = null;
            }
            $credito->save();
        }

        // 4. Si la venta estaba en DEVOLUCION y esta es la única devolución activa,
        //    volver al estado original (PAGADA o CREDITO)
        if ($venta->estado === 'DEVOLUCION') {
            $otrasActivas = DevolucionVenta::where('venta_id', $venta->id)
                ->where('estado', 'DEVUELTA')
                ->where('id', '!=', $devolucion->id)
                ->exists();

            if (!$otrasActivas) {
                $venta->estado = $venta->credito ? 'CREDITO' : 'PAGADA';
                $venta->save();
            }
        }

        // 5. Marcar la devolución como ANULADA
        $devolucion->update(['estado' => 'ANULADA']);

        DB::commit();

        $devolucion->load([
            'detalleDevolucionVentas.producto',
            'detalleDevolucionVentas.detalleVenta.lote',
            'detalleDevolucionVentas.productoDaniado',
            'venta'
        ]);

        return response()->json([
            'message'    => 'Devolución anulada correctamente.',
            'devolucion' => $devolucion
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'message' => 'Error al anular la devolución.',
            'error'   => $e->getMessage()
        ], 500);
    }

    }
}
