<?php

namespace App\Http\Controllers;

use App\Models\CambioProducto;
use App\Models\ProductoDaniado;
use App\Models\Producto;
use App\Models\Lote;
use Illuminate\Support\Facades\DB;
use Exception;
use App\Http\Requests\CambioProductoRequest;

class CambioProductoController extends Controller
{
    public function index(CambioProductoRequest $request)
    {
        try {
            $query = CambioProducto::with(['producto.marca', 'productoReemplazo.marca', 'lote', 'producto.categoria']);

            if ($request->filled('estado_reclamacion')) {
                $query->where('estado_reclamacion', $request->estado_reclamacion);
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
                'message' => 'Error al obtener los registros de cambio de producto.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function store(CambioProductoRequest $request)
    {
        try {
            DB::beginTransaction();

            $producto = Producto::findOrFail($request->producto_id);
            $cantidad = $request->cantidad;
            $loteId = $request->lote_id;

            if ($producto->stock < $cantidad) {
                return response()->json([
                    'message' => "Stock insuficiente del producto. Stock disponible: {$producto->stock}."
                ], 400);
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
                if ($lote->cantidad_actual <= 0) {
                    $lote->estado = 'INACTIVO';
                    $lote->motivo_inactivo = 'AGOTADO';
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

            $cambioProducto = CambioProducto::create([
                'producto_id'        => $producto->id,
                'lote_id'            => $loteId,
                'cantidad'           => $cantidad,
                'descripcion'        => $request->descripcion,
                'fecha'              => now(),
                'costo_unitario'     => $costoUnitario,
                'total_perdida'      => $totalPerdida,
                'estado_reclamacion' => 'PENDIENTE',
                'reemplazo'          => null,
            ]);

            DB::commit();

            return response()->json([
                'message'        => 'Cambio de producto registrado correctamente.',
                'cambio_producto' => $cambioProducto->load(['producto.marca', 'lote'])
            ], 201);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al registrar el cambio de producto.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $registro = CambioProducto::with(['producto.marca', 'productoReemplazo.marca', 'lote'])->findOrFail($id);
            return response()->json([
                'cambio_producto' => $registro
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

            $registro = CambioProducto::findOrFail($id);

            if ($registro->estado_reclamacion !== 'PENDIENTE') {
                return response()->json([
                    'message' => 'Solo se pueden anular registros que estén en estado PENDIENTE.'
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
                'message'        => 'Cambio anulado correctamente.',
                'cambio_producto' => $registro->load(['producto.marca', 'productoReemplazo.marca', 'lote'])
            ], 200);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al anular el registro.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function aceptar(CambioProductoRequest $request, $id)
    {
        try {
            DB::beginTransaction();

            $registro = CambioProducto::findOrFail($id);

            if ($registro->estado_reclamacion !== 'PENDIENTE') {
                return response()->json([
                    'message' => 'Solo se pueden aceptar reclamos que estén en estado PENDIENTE.'
                ], 400);
            }

            $productoOriginal = $registro->producto;
            $cantidad = $registro->cantidad;
            $tipo = $request->tipo;

            // Determinar el producto que se recibe como reemplazo
            if ($tipo === 'mismo') {
                $productoRecibido = $productoOriginal;
                $reemplazo = 'MISMO_PRODUCTO';
            } else {
                $reemplazo = 'DIFERENTE_PRODUCTO';

                // Si se manda crear_producto, se crea el producto nuevo
                if ($request->crear_producto) {
                    $productoRecibido = Producto::create([
                        'nombre'         => $request->nombre,
                        'precio_detalle' => $request->precio_detalle,
                        'precio_mayor'   => $request->precio_mayor,
                        'costo_promedio' => $request->costo_promedio ?: 0,
                        'stock'          => 0,
                        'stock_minimo'   => $request->stock_minimo,
                        'perecedero'     => $request->perecedero,
                        'estado'         => 'ACTIVO',
                        'marca_id'       => $request->marca_id,
                        'categoria_id'   => $request->categoria_id,
                        'seccion'        => $request->seccion,
                    ]);
                } else {
                    $productoRecibido = Producto::findOrFail($request->producto_reemplazo_id);
                }
            }

            // Sumar la cantidad al stock del producto recibido
            $productoRecibido->increment('stock', $cantidad);

            // Manejo del lote si el producto recibido es perecedero
            $loteId = $registro->lote_id;
            if ($productoRecibido->perecedero === 'PERECEDERO') {
                $loteOpcion = $request->lote_opcion ?? 'nuevo';

                // Opción "mismo lote": reactivar el lote original del registro (solo aplica a tipo mismo)
                if ($loteOpcion === 'mismo' && $tipo === 'mismo' && $registro->lote_id) {
                    $loteOriginal = $registro->lote;
                    if ($loteOriginal) {
                        $loteOriginal->cantidad_actual += $cantidad;
                        if ($loteOriginal->estado === 'INACTIVO') {
                            $loteOriginal->estado = 'ACTIVO';
                            $loteOriginal->motivo_inactivo = null;
                        }
                        $loteOriginal->save();
                        $loteId = $loteOriginal->id;
                    }
                } else {
                    $nuevoLote = Lote::create([
                        'codigo_lote'       => $request->codigo_lote,
                        'fecha_vencimiento' => $request->fecha_vencimiento,
                        'fecha_ingreso'     => now(),
                        'cantidad_inicial'  => $cantidad,
                        'cantidad_actual'   => $cantidad,
                        'estado'            => 'ACTIVO',
                        'motivo_inactivo'   => null,
                        'producto_id'       => $productoRecibido->id,
                    ]);
                    $loteId = $nuevoLote->id;
                }
            }

            $registro->estado_reclamacion = 'ACEPTADO';
            $registro->reemplazo = $reemplazo;
            $registro->producto_reemplazo_id = ($tipo === 'diferente') ? $productoRecibido->id : null;
            $registro->lote_id = $loteId;
            $registro->save();

            DB::commit();

            return response()->json([
                'message'        => 'Reemplazo aceptado correctamente.',
                'cambio_producto' => $registro->load(['producto.marca', 'productoReemplazo.marca', 'lote'])
            ], 200);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al aceptar la reclamación.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function rechazar($id)
    {
        try {
            DB::beginTransaction();

            $registro = CambioProducto::findOrFail($id);

            if ($registro->estado_reclamacion !== 'PENDIENTE') {
                return response()->json([
                    'message' => 'Solo se pueden rechazar reclamos que estén en estado PENDIENTE.'
                ], 400);
            }

            $cantidad = $registro->cantidad;

            $registro->update([
                'estado_reclamacion' => 'RECHAZADO'
            ]);

            $productoDaniado = ProductoDaniado::create([
                'producto_id'        => $registro->producto_id,
                'lote_id'            => $registro->lote_id,
                'cantidad'           => $cantidad,
                'descripcion'        => $registro->descripcion,
                'fecha'              => now(),
                'costo_unitario'     => $registro->costo_unitario,
                'total_perdida'      => $registro->total_perdida,
                'estado'             => 'DANIADO',
                'origen'             => 'PROVEEDOR',
                'estado_reclamacion' => 'RECHAZADO',
                'reemplazo'          => null,
            ]);

            DB::commit();

            return response()->json([
                'message'          => 'Reclamación rechazada correctamente.',
                'cambio_producto'  => $registro->load(['producto.marca', 'lote']),
                'producto_daniado' => $productoDaniado->load(['producto.marca', 'lote'])
            ], 200);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al rechazar la reclamación.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}
