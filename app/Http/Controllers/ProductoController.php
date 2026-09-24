<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\ProductoRequest;
use App\Models\Producto;
use App\Models\Lote;
use App\Models\AjusteStock;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class ProductoController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = Producto::with([
                'categoria',
                'marca',
                'ultimoDetalleCompra',
                'lotes'
            ]);
            //Filtro por sección
            if ($request->filled('seccion')) {
                $query->where('seccion', $request->seccion);
            }

            // Filtro por estado
            $estado = $request->query('estado');
            if ($estado !== null && in_array($estado, ['ACTIVO', 'INACTIVO'])) {
                $query->where('estado', $estado);
            }

            // Búsqueda por Nombre, categoría y marca
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('productos.nombre', 'ilike', '%' . $search . '%')
                    ->orWhereHas('categoria', function ($q2) use ($search) {
                        $q2->where('nombre', 'ilike', '%' . $search . '%');
                        })
                        ->orWhereHas('marca', function ($q2) use ($search) {
                            $q2->where('nombre', 'ilike', '%' . $search . '%');
                        });
                });
            }

            if ($request->filled('categoria_id')) {
                $query->where('categoria_id', $request->categoria_id);
            }

            if ($request->filled('marca_id')) {
                $query->where('marca_id', $request->marca_id);
            }

            if ($request->boolean('sin_paginar')) {
                $productos = $query->orderBy('id', 'desc')->get();
            } else {
                $perPage = min((int) $request->get('per_page', 10), 200);
                $productos = $query->orderBy('id', 'desc')->paginate($perPage);
            }


            return response()->json($productos, 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener los productos.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(ProductoRequest $request)
    {
        try {
            DB::beginTransaction();

            $stockInicial = 0;
            if ($request->perecedero === 'PERECEDERO') {
                $lotesInput = $request->lotes ?: [];
                foreach ($lotesInput as $itemLote) {
                    $stockInicial += (int) ($itemLote['cantidad'] ?? 0);
                }
            } else {
                $stockInicial = (int) $request->cantidad_inicial;
            }

            $producto = Producto::create([
                'nombre'           => $request->nombre,
                'precio_detalle'   => $request->precio_detalle,
                'precio_mayor'     => $request->precio_mayor,
                'costo_promedio'   => 0,
                'stock'            => $stockInicial,
                'stock_minimo'     => $request->stock_minimo,
                'perecedero'       => $request->perecedero,
                'estado'           => 'ACTIVO',
                'marca_id'         => $request->marca_id,
                'categoria_id'     => $request->categoria_id,
                'seccion'          => $request->seccion
            ]);

            if ($request->perecedero === 'PERECEDERO' && !empty($lotesInput)) {
                foreach ($lotesInput as $itemLote) {
                    $cant = (int) $itemLote['cantidad'];
                    Lote::create([
                        'fecha_vencimiento' => $itemLote['fecha_vencimiento'],
                        'codigo_lote'       => mb_strtoupper(trim($itemLote['codigo_lote']), 'UTF-8'),
                        'fecha_ingreso'     => now(),
                        'cantidad_inicial'  => $cant,
                        'cantidad_actual'   => $cant,
                        'estado'            => 'ACTIVO',
                        'producto_id'       => $producto->id
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'message'  => 'Producto creado correctamente.',
                'producto' => $producto->load('lotes')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error interno del servidor.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(string $id)
    {
        try {
            $producto = Producto::findOrFail($id);
            return response()->json($producto, 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Producto no encontrado.'
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener el producto.'
            ], 500);
        }
    }

    public function update(ProductoRequest $request, string $id)
    {
        try {
            DB::beginTransaction();

            $producto = Producto::findOrFail($id);

            // Construir el array base con los campos que siempre se actualizan
            $data = [
                'nombre'           => $request->nombre,
                'precio_detalle'   => $request->precio_detalle,
                'precio_mayor'     => $request->precio_mayor,
                'stock_minimo'     => $request->stock_minimo,
                'marca_id'         => $request->marca_id,
                'categoria_id'     => $request->categoria_id,
            ];

            if ($request->has('seccion')) {
                $data['seccion'] = $request->seccion;
            }

            // Manejo de Ajuste de Stock
            if ($request->has('nuevo_stock') && $request->nuevo_stock !== null) {
                $stockAnterior = $producto->stock;
                $nuevoStockValor = (int) $request->nuevo_stock;
                $loteId = $request->lote_id;

                if ($producto->perecedero === 'PERECEDERO') {
                    if (!$loteId) {
                        return response()->json([
                            'message' => 'Debe seleccionar un lote para ajustar el stock de un producto perecedero.'
                        ], 422);
                    }

                    $lote = Lote::where('id', $loteId)->where('producto_id', $producto->id)->first();
                    if (!$lote) {
                        return response()->json([
                            'message' => 'El lote seleccionado no existe o no pertenece a este producto.'
                        ], 422);
                    }

                    // Para perecedero, el nuevo_stock recibido representa la nueva cantidad del lote seleccionado
                    $cantidadAnteriorLote = $lote->cantidad_actual;
                    if ($nuevoStockValor !== $cantidadAnteriorLote) {
                        $diferencia = abs($nuevoStockValor - $cantidadAnteriorLote);
                        $tipoAjuste = $nuevoStockValor > $cantidadAnteriorLote ? 'INCREMENTO' : 'DECREMENTO';

                        // Validación: NO se permite incrementar stock en un lote vencido
                        if ($tipoAjuste === 'INCREMENTO' && $lote->fecha_vencimiento < now()->toDateString()) {
                            return response()->json([
                                'message' => "No se puede incrementar el stock en el lote '{$lote->codigo_lote}' porque se encuentra vencido (venció el {$lote->fecha_vencimiento})."
                            ], 422);
                        }

                        // Actualizar el lote
                        $lote->cantidad_actual = $nuevoStockValor;
                        if ($lote->cantidad_actual <= 0) {
                            $lote->estado = 'INACTIVO';
                            $lote->motivo_inactivo = 'AGOTADO';
                        } else {
                            $lote->estado = 'ACTIVO';
                            $lote->motivo_inactivo = null;
                        }
                        $lote->save();

                        // Recalcular stock total del producto basado en la sumatoria de sus lotes
                        $nuevoStockTotal = (int) Lote::where('producto_id', $producto->id)->sum('cantidad_actual');
                        $data['stock'] = $nuevoStockTotal;

                        if ($nuevoStockTotal === 0) {
                            $data['estado'] = 'INACTIVO';
                        } elseif ($stockAnterior === 0 && $nuevoStockTotal > 0) {
                            $data['estado'] = 'ACTIVO';
                        }

                        // Registrar auditoría en la tabla ajustes_stock
                        AjusteStock::create([
                            'producto_id'    => $producto->id,
                            'lote_id'        => $lote->id,
                            'tipo_ajuste'    => $tipoAjuste,
                            'cantidad'       => $diferencia,
                            'stock_anterior' => $stockAnterior,
                            'stock_nuevo'    => $nuevoStockTotal,
                            'motivo'         => $request->motivo_ajuste ?: 'Ajuste manual de stock por lote',
                        ]);
                    }
                } else {
                    // Para productos normales (NO perecederos)
                    if ($nuevoStockValor !== $stockAnterior) {
                        $diferencia = abs($nuevoStockValor - $stockAnterior);
                        $tipoAjuste = $nuevoStockValor > $stockAnterior ? 'INCREMENTO' : 'DECREMENTO';

                        $data['stock'] = $nuevoStockValor;
                        if ($nuevoStockValor === 0) {
                            $data['estado'] = 'INACTIVO';
                        } elseif ($stockAnterior === 0 && $nuevoStockValor > 0) {
                            $data['estado'] = 'ACTIVO';
                        }

                        AjusteStock::create([
                            'producto_id'    => $producto->id,
                            'lote_id'        => null,
                            'tipo_ajuste'    => $tipoAjuste,
                            'cantidad'       => $diferencia,
                            'stock_anterior' => $stockAnterior,
                            'stock_nuevo'    => $nuevoStockValor,
                            'motivo'         => $request->motivo_ajuste ?: 'Ajuste manual de stock',
                        ]);
                    }
                }
            }

            // Manejo de Agregar Nuevo Lote en Edición
            if ($request->has('nuevo_lote') && !empty($request->nuevo_lote) && $producto->perecedero === 'PERECEDERO') {
                $nl = $request->nuevo_lote;
                $cantNuevo = (int) $nl['cantidad'];

                $nuevoLoteModel = Lote::create([
                    'fecha_vencimiento' => $nl['fecha_vencimiento'],
                    'codigo_lote'       => mb_strtoupper(trim($nl['codigo_lote']), 'UTF-8'),
                    'fecha_ingreso'     => now(),
                    'cantidad_inicial'  => $cantNuevo,
                    'cantidad_actual'   => $cantNuevo,
                    'estado'            => 'ACTIVO',
                    'producto_id'       => $producto->id
                ]);

                $stockActualProd = isset($data['stock']) ? $data['stock'] : $producto->stock;
                $nuevoStockTotal = $stockActualProd + $cantNuevo;
                $data['stock'] = $nuevoStockTotal;
                if ($nuevoStockTotal > 0) {
                    $data['estado'] = 'ACTIVO';
                }

                AjusteStock::create([
                    'producto_id'    => $producto->id,
                    'lote_id'        => $nuevoLoteModel->id,
                    'tipo_ajuste'    => 'INCREMENTO',
                    'cantidad'       => $cantNuevo,
                    'stock_anterior' => $stockActualProd,
                    'stock_nuevo'    => $nuevoStockTotal,
                    'motivo'         => 'Ingreso de nuevo lote al editar producto',
                ]);
            }

            $producto->update($data);

            DB::commit();

            return response()->json([
                'message'  => 'Producto actualizado correctamente.',
                'producto' => $producto->load('lotes')
            ], 200);

        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json(['message' => 'Producto no encontrado.'], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error interno del servidor.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function alertaStockMinimo()
    {
        try {
            $productos = Producto::where('estado', 'ACTIVO')
                ->whereColumn('stock', '<=', 'stock_minimo')
                ->get()
                ->map(function ($producto) {
                    return [
                        'id'      => $producto->id,
                        'nombre'  => $producto->nombre,
                        'stock'   => $producto->stock,
                        'mensaje' => "El producto {$producto->nombre} está a punto de agotarse, quedan {$producto->stock} unidades."
                    ];
                });

            return response()->json($productos, 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener los productos con stock mínimo.'
            ], 500);
        }
    }
    public function cambiarEstado($id)
    {
        try {
            $producto = Producto::findOrFail($id);
            $nuevoEstado = $producto->estado === 'ACTIVO' ? 'INACTIVO' : 'ACTIVO';
            $producto->update(['estado' => $nuevoEstado]);

            return response()->json([
                'message' => 'Estado actualizado correctamente',
                'estado'  => $nuevoEstado
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al cambiar el estado',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function verificarNombre(Request $request)
    {
        try {
            $nombre = $request->query('nombre');
            $id = $request->query('id');

            if (!$nombre) {
                return response()->json(['existe' => false], 200);
            }

            $nombreNormalizado = $this->normalizarNombre($nombre);
            $query = Producto::select('id', 'nombre');
            if ($id) {
                $query->where('id', '!=', $id);
            }

            $productoExistente = $query->get()->first(function ($prod) use ($nombreNormalizado) {
                return $this->normalizarNombre($prod->nombre) === $nombreNormalizado;
            });

            if ($productoExistente) {
                return response()->json([
                    'existe' => true,
                    'producto' => [
                        'id' => $productoExistente->id,
                        'nombre' => $productoExistente->nombre
                    ]
                ], 200);
            }

            return response()->json(['existe' => false], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al verificar el nombre del producto.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function ajustesStock(string $id)
    {
        try {
            $ajustes = AjusteStock::with('lote')
                ->where('producto_id', $id)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json($ajustes, 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener el historial de ajustes de stock.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    private function normalizarNombre(?string $nombre): string
    {
        if (!$nombre) {
            return '';
        }
        $texto = mb_strtolower(trim($nombre), 'UTF-8');
        $texto = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ü', 'ñ'],
            ['a', 'e', 'i', 'o', 'u', 'u', 'n'],
            $texto
        );
        return preg_replace('/[^a-z0-9]/', '', $texto);
    }
}

