<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Producto;
use App\Models\ProductoDaniado;
use App\Models\Lote;
use Illuminate\Support\Facades\DB;
use Exception;

class ProductoDaniadoController extends Controller
{
    /**
     * Display a listing of damaged products.
     */
    public function index(Request $request)
    {
        try {
            $request->validate([
                'per_page'           => 'nullable|integer|min:1|max:100',
                'estado_reclamacion' => 'nullable|in:PENDIENTE,ACEPTADO,RECHAZADO,ANULADO',
                'fecha_inicio'       => 'nullable|date',
                'fecha_fin'          => 'nullable|date|after_or_equal:fecha_inicio',
                'buscar'             => 'nullable|string',
            ]);

            $query = ProductoDaniado::with(['producto', 'lote']);

            // Filtro por estado de reclamación
            if ($request->filled('estado_reclamacion')) {
                $query->where('estado_reclamacion', $request->estado_reclamacion);
            }

            // Filtro por rango de fechas
            if ($request->filled('fecha_inicio')) {
                $query->whereDate('fecha', '>=', $request->fecha_inicio);
            }
            if ($request->filled('fecha_fin')) {
                $query->whereDate('fecha', '<=', $request->fecha_fin);
            }

            // Filtro por búsqueda (nombre del producto o descripción)
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
            $registros = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json($registros, 200);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Error al obtener los registros de productos dañados.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created damaged product registration.
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'producto_id' => 'required|exists:productos,id',
                'cantidad'    => 'required|integer|min:1',
                'descripcion' => 'required|string|max:255',
                'lote_id'     => 'nullable|exists:lotes,id',
            ]);

            DB::beginTransaction();

            $producto = Producto::findOrFail($request->producto_id);
            $cantidad = $request->cantidad;
            $loteId = $request->lote_id;

            // Validación de stock general
            if ($producto->stock < $cantidad) {
                return response()->json([
                    'message' => "Stock insuficiente del producto. Stock disponible: {$producto->stock}."
                ], 400);
            }

            // Validación y descuento para productos perecederos (lotes)
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

                // Descontar del lote
                $lote->cantidad_actual -= $cantidad;
                if ($lote->cantidad_actual <= 0) {
                    $lote->estado = 'INACTIVO';
                    $lote->motivo_inactivo = 'AGOTADO';
                }
                $lote->save();
            } else {
                // Si no es perecedero, no debería enviar lote
                $loteId = null;
            }

            // Descontar del stock del producto
            $producto->decrement('stock', $cantidad);

            // Obtener el costo unitario (fallbacks: costo_promedio o el último precio de compra, o 0)
            $costoUnitario = $producto->costo_promedio ?: 0.00;
            if ($costoUnitario <= 0 && $producto->ultimoDetalleCompra) {
                $costoUnitario = $producto->ultimoDetalleCompra->precio_unitario;
            }

            $totalPerdida = $costoUnitario * $cantidad;

            // Crear el registro de producto dañado
            $productoDaniado = ProductoDaniado::create([
                'producto_id'        => $producto->id,
                'lote_id'            => $loteId,
                'cantidad'           => $cantidad,
                'descripcion'        => $request->descripcion,
                'fecha'              => now(),
                'costo_unitario'     => $costoUnitario,
                'total_perdida'      => $totalPerdida,
                'estado'             => 'DANIADO', // Registro directo
                'estado_reclamacion' => 'PENDIENTE',
            ]);

            DB::commit();

            return response()->json([
                'message'          => 'Producto dañado registrado correctamente.',
                'producto_daniado' => $productoDaniado->load(['producto', 'lote'])
            ], 201);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al registrar el producto dañado.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified damaged product.
     */
    public function show($id)
    {
        try {
            $registro = ProductoDaniado::with(['producto', 'lote'])->findOrFail($id);
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

    /**
     * Annul a damaged product registration. Reverts stock changes.
     */
    public function anular($id)
    {
        try {
            DB::beginTransaction();

            $registro = ProductoDaniado::findOrFail($id);

            if ($registro->estado_reclamacion !== 'PENDIENTE') {
                return response()->json([
                    'message' => 'Solo se pueden anular registros que estén en estado PENDIENTE.'
                ], 400);
            }

            $producto = $registro->producto;
            $cantidad = $registro->cantidad;

            // Devolver al stock general
            $producto->increment('stock', $cantidad);

            // Devolver al lote si es perecedero
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
                'message'          => 'Registro de producto dañado anulado correctamente. El stock ha sido restaurado.',
                'producto_daniado' => $registro->load(['producto', 'lote'])
            ], 200);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al anular el registro.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Accept supplier replacement. Restores stock. If perishable, requires new expiration and lot code.
     */
    public function aceptar(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $registro = ProductoDaniado::findOrFail($id);

            if ($registro->estado_reclamacion !== 'PENDIENTE') {
                return response()->json([
                    'message' => 'Solo se pueden aceptar reclamos que estén en estado PENDIENTE.'
                ], 400);
            }

            $producto = $registro->producto;
            $cantidad = $registro->cantidad;

            if ($producto->perecedero === 'PERECEDERO') {
                $request->validate([
                    'codigo_lote'       => 'required|string|max:50',
                    'fecha_vencimiento' => 'required|date|after:today',
                ]);

                // Crear un nuevo lote para el producto devuelto
                $nuevoLote = Lote::create([
                    'codigo_lote'       => $request->codigo_lote,
                    'fecha_vencimiento' => $request->fecha_vencimiento,
                    'fecha_ingreso'     => now(),
                    'cantidad_inicial'  => $cantidad,
                    'cantidad_actual'   => $cantidad,
                    'estado'            => 'ACTIVO',
                    'motivo_inactivo'   => null,
                    'producto_id'       => $producto->id,
                ]);

                // Vincular el registro con el lote (opcionalmente guardamos el nuevo lote o conservamos el historial)
                // Vamos a actualizar el lote_id en el registro para apuntar al nuevo lote recibido
                $registro->lote_id = $nuevoLote->id;
            }

            // Incrementar stock general
            $producto->increment('stock', $cantidad);

            $registro->estado_reclamacion = 'ACEPTADO';
            $registro->save();

            DB::commit();

            return response()->json([
                'message'          => 'Reclamación aceptada correctamente. El stock ha sido actualizado.',
                'producto_daniado' => $registro->load(['producto', 'lote'])
            ], 200);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al aceptar la reclamación.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject supplier replacement. The product is definitively lost.
     */
    public function rechazar($id)
    {
        try {
            DB::beginTransaction();

            $registro = ProductoDaniado::findOrFail($id);

            if ($registro->estado_reclamacion !== 'PENDIENTE') {
                return response()->json([
                    'message' => 'Solo se pueden rechazar reclamos que estén en estado PENDIENTE.'
                ], 400);
            }

            // Si es rechazado, no se devuelve nada al stock (ya se descontó al registrar).
            $registro->update([
                'estado_reclamacion' => 'RECHAZADO'
            ]);

            DB::commit();

            return response()->json([
                'message'          => 'Reclamación rechazada correctamente. Los productos se marcan como pérdida definitiva.',
                'producto_daniado' => $registro->load(['producto', 'lote'])
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
