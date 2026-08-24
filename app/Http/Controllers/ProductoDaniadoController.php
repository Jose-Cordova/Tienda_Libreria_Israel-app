<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use App\Models\ProductoDaniado;
use App\Models\Lote;
use Illuminate\Support\Facades\DB;
use Exception;
use App\Http\Requests\ProductoDaniadoRequest;

class ProductoDaniadoController extends Controller
{
    public function index(ProductoDaniadoRequest $request)
    {
        try {
            $query = ProductoDaniado::with(['producto.marca', 'lote', 'producto.categoria']);

            if ($request->filled('origen')) {
                $query->where('origen', $request->origen);
            }

            if ($request->filled('estado_reclamacion')) {
                $query->where('estado_reclamacion', $request->estado_reclamacion);
            }

            if ($request->filled('estado')) {
                $query->where('estado', $request->estado);
            }

            if ($request->filled('fecha_inicio')) {
                $query->whereDate('fecha', '>=', $request->fecha_inicio);
            }
            if ($request->filled('fecha_fin')) {
                $query->whereDate('fecha', '<=', $request->fecha_fin);
            }

            if ($request->filled('buscar')) {
                $buscar = $request->buscar;
                $query->where(function ($q) use ($buscar) {
                    $q->whereRaw('LOWER(descripcion) LIKE ?', ["%" . strtolower($buscar) . "%"])
                      ->orWhereHas('producto', function ($pq) use ($buscar) {
                          $pq->whereRaw('LOWER(nombre) LIKE ?', ["%" . strtolower($buscar) . "%"]);
                      });
                });
            }

            $perPage = $request->get('per_page', 10);
            $registros = $query->orderBy('fecha', 'desc')->orderBy('id', 'desc')->paginate($perPage);

            return response()->json($registros, 200);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Error al obtener los registros de productos dañados.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function lotesVencidos(ProductoDaniadoRequest $request)
    {
        try {
            $query = Lote::with('producto')
                ->where('estado', 'ACTIVO')
                ->where('cantidad_actual', '>', 0)
                ->whereNotNull('fecha_vencimiento')
                ->whereDate('fecha_vencimiento', '<', now()->toDateString());

            if ($request->filled('producto_id')) {
                $query->where('producto_id', $request->producto_id);
            }

            $lotes = $query->orderBy('fecha_vencimiento')->get();

            return response()->json($lotes, 200);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Error al obtener los lotes vencidos.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function store(ProductoDaniadoRequest $request)
    {
        try {
            DB::beginTransaction();

            $producto = Producto::findOrFail($request->producto_id);
            $cantidad = $request->cantidad;
            $loteId = $request->lote_id;
            $origen = $request->origen;

            if ($producto->stock < $cantidad) {
                return response()->json([
                    'message' => "Stock insuficiente del producto. Stock disponible: {$producto->stock}."
                ], 400);
            }

            if ($origen === 'VENCIMIENTO') {
                if (!$loteId) {
                    return response()->json([
                        'message' => 'Debes seleccionar el lote vencido.'
                    ], 400);
                }

                $lote = Lote::where('id', $loteId)->where('producto_id', $producto->id)->first();
                if (!$lote) {
                    return response()->json([
                        'message' => 'El lote seleccionado no pertenece a este producto.'
                    ], 400);
                }

                if ($lote->estado !== 'ACTIVO' || $lote->cantidad_actual <= 0) {
                    return response()->json([
                        'message' => 'El lote no tiene stock disponible.'
                    ], 400);
                }

                if (!$lote->fecha_vencimiento || $lote->fecha_vencimiento >= now()->toDateString()) {
                    return response()->json([
                        'message' => 'El lote seleccionado no está vencido.'
                    ], 400);
                }

                $cantidad = $lote->cantidad_actual;
            }

            if ($producto->perecedero === 'PERECEDERO') {
                if (!$loteId) {
                    return response()->json([
                        'message' => 'El lote es obligatorio para productos perecederos.'
                    ], 400);
                }

                $lote = Lote::where('id', $loteId)->where('producto_id', $producto->id)->first();
                if (!$lote) {
                    return response()->json([
                        'message' => 'El lote seleccionado no pertenece a este producto.'
                    ], 400);
                }

                if ($lote->cantidad_actual < $cantidad) {
                    return response()->json([
                        'message' => "Stock insuficiente en el lote seleccionado. Disponible: {$lote->cantidad_actual}."
                    ], 400);
                }

                $lote->cantidad_actual -= $cantidad;
                if ($lote->cantidad_actual <= 0 || $origen === 'VENCIMIENTO') {
                    $lote->estado = 'INACTIVO';
                    $lote->motivo_inactivo = $origen === 'VENCIMIENTO' ? 'VENCIMIENTO' : 'AGOTADO';
                }
                $lote->save();
            } else {
                $loteId = null;
            }

            $producto->decrement('stock', $cantidad);

            $costoUnitario = $producto->costo_promedio ?: 0.00;
            if ($costoUnitario <= 0 && $producto->ultimoDetalleCompra) {
                $costoUnitario = $producto->ultimoDetalleCompra->precio_unitario;
            }

            $totalPerdida = $costoUnitario * $cantidad;

            $productoDaniado = ProductoDaniado::create([
                'producto_id'        => $producto->id,
                'lote_id'            => $loteId,
                'cantidad'           => $cantidad,
                'descripcion'        => $request->descripcion,
                'fecha'              => now(),
                'costo_unitario'     => $costoUnitario,
                'total_perdida'      => $totalPerdida,
                'estado'             => 'DANIADO',
                'origen'             => $origen,
                'estado_reclamacion' => 'REGISTRADO',
                'reemplazo'          => null,
            ]);

            DB::commit();

            return response()->json([
                'message'          => 'Producto registrado como dañado correctamente.',
                'producto_daniado' => $productoDaniado->load(['producto.marca', 'lote'])
            ], 201);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al registrar.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $registro = ProductoDaniado::with(['producto.marca', 'lote'])->findOrFail($id);
            return response()->json([
                'producto_daniado' => $registro
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Registro no encontrado.',
                'error'   => $e->getMessage()
            ], 404);
        }
    }

    public function anular($id)
    {
        try {
            DB::beginTransaction();

            $registro = ProductoDaniado::findOrFail($id);

            if ($registro->estado_reclamacion !== 'REGISTRADO') {
                return response()->json([
                    'message' => 'Solo se pueden anular registros que estén en estado REGISTRADO.'
                ], 400);
            }

            if ($registro->origen === 'VENTA') {
                return response()->json([
                    'message' => 'Los registros originados por devolución de venta no se pueden anular desde aquí.'
                ], 400);
            }

            if ($registro->origen === 'VENCIMIENTO') {
                return response()->json([
                    'message' => 'Los registros por vencimiento no se pueden anular.'
                ], 400);
            }

            $producto = $registro->producto;
            $cantidad = $registro->cantidad;

            $producto->increment('stock', $cantidad);

            if ($registro->lote_id) {
                $lote = $registro->lote;
                if ($lote) {
                    $lote->cantidad_actual += $cantidad;
                    if ($lote->estado === 'INACTIVO') {
                        $lote->estado = 'ACTIVO';
                        $lote->motivo_inactivo = null;
                    }
                    $lote->save();
                }
            }

            $registro->update([
                'estado_reclamacion' => 'ANULADO'
            ]);

            DB::commit();

            return response()->json([
                'message'          => 'Registro anulado correctamente.',
                'producto_daniado' => $registro->load(['producto.marca', 'lote'])
            ], 200);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al anular el registro.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}
