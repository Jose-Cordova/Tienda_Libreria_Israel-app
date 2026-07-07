<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\CompraRequest;
use App\Models\Compra;
use App\Models\DetalleCompra;
use App\Models\Producto;
use App\Models\Lote;

class CompraController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try{
            $paginacion = $request->query('paginacion', 10);
            $buscar = $request->query('buscar');
            $estado = $request->query('estado');
            $proveedorId = $request->query('proveedor_id');
            $fechaInicio = $request->query('fecha_inicio');
            $fechaFin = $request->query('fecha_fin');

            //Iniciamos la consulta con las relaciones nesesarias
            $query = Compra::with(['user', 'proveedor']);

            //Filtro de busqueda
            if($buscar){
                $query->where(function($q) use ($buscar){
                    $q->whereRaw('LOWER(numero_factura) LIKE ?', ["%" . strtolower($buscar) . "%"])
                    ->orWhereHas('proveedor', function($pq) use ($buscar){
                        $pq->whereRaw('LOWER(nombre) LIKE ?', ["%" . strtolower($buscar) . "%"]);
                    });
                });
            }
            //Filtro por estado
            if($estado){
                $query->where('estado', $estado);
            }
            //Filtro por proveedor
            if($proveedorId){
                $query->where('proveedor_id', $proveedorId);
            }

            //Filtro por fechas
            if($fechaInicio){
                $query->whereDate('fecha_registro', '>=', $fechaInicio);
            }
            if($fechaFin){
                $query->whereDate('fecha_registro', '<=', $fechaFin);
            }

            $compras = $query->orderBy('id', 'desc')->paginate($paginacion);
            return response()->json($compras, 200);

        }catch(\Exception $e){
            return response()->json([
                'message' => 'Error al obtener las categorias.'
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CompraRequest $request)
    {
        //Iniciamos la transacion
        DB::beginTransaction();

        try{
            //Creamos el encabesado de la compra
            $compra = Compra::create([
                'numero_factura' => $request->numero_factura,
                'codigo_factura' => $request->codigo_factura,
                'fecha_emision' => $request->fecha_emision,
                'fecha_registro' => now(),
                'total' => 0,
                'estado' => 'REGISTRADA',
                'proveedor_id' => $request->proveedor_id,
                'user_id' => auth()->id()
            ]);

            $totalCompra = 0;

            //Prosesamos cada detalle de la compra
            foreach($request->detalles as $detalle){
                $factorConversion = $detalle['factor_conversion'] ?? 1;
                //Si el producto es nuevo lo creamos
                if(is_null($detalle['producto_id'])){
                    $producto = Producto::create([
                        'nombre' => $detalle['nombre'],
                        'precio_detalle' => 0,
                        'precio_mayor' => 0,
                        'costo_promedio' => 0,
                        'stock' => 0,
                        'stock_minimo' => $detalle['stock_minimo'],
                        'perecedero' => $detalle['perecedero'],
                        'sesion' => $detalle['sesion'],
                        'estado' => 'ACTIVO',
                        'marca_id' => $detalle['marca_id'],
                        'categoria_id' => $detalle['categoria_id']
                    ]);

                }else{
                    //Si el producto existe lo buscamos
                    $producto = Producto::findOrFail($detalle['producto_id']);

                    //Si el producto estaba inacativo lo activamos
                    if($producto->estado === 'INACTIVO'){
                        $producto->update(['estado' => 'ACTIVO']);
                    }

                }

                //Si el producto es perecedero se crea el lote correspondiente
                if($producto->perecedero === 'PERECEDERO'){
                    //Para perecederos la cantidad total es la suma de todos los lotes
                    $cantidad = array_sum(array_column($detalle['lotes'], 'cantidad'));
                    $cantidadTotal = $cantidad * $factorConversion;
                    //Creamos cada lote del detalle
                    foreach($detalle['lotes'] as $lote){
                        $cantidadLote = $lote['cantidad'] * $factorConversion;
                        Lote::create([
                            'codigo_lote' => $lote['codigo_lote'],
                            'fecha_vencimiento' => $lote['fecha_vencimiento'],
                            'fecha_ingreso' => now(),
                            'cantidad_inicial' => $cantidadLote,
                            'cantidad_actual' => $cantidadLote,
                            'estado' => 'ACTIVO',
                            'motivo_inactivo' => null,
                            'producto_id' => $producto->id,
                            'compra_id' => $compra->id
                        ]);
                    }

                }else{
                    //Para productos normales la cantidad viene directo en el detalle
                    $cantidadTotal = $detalle['cantidad'] * $factorConversion;
                }

                //Obtenemos los datos antes del incremento del stock
                $stockAnterior = $producto->stock;
                $cppAnterior = $producto->costo_promedio;
                //Calculamos los precios de venta al detalle y al mayor y actualizamos
                $factorConversion = $detalle['factor_conversion'] ?? 1;
                $precioUnitarioBase = $detalle['precio_unitario'] / $factorConversion;
                //Aplicamos formula del CPP
                $totalUnidadesNuevas = $stockAnterior + $cantidadTotal;
                if($totalUnidadesNuevas > 0){
                    $nuevoCpp = (($stockAnterior * $cppAnterior) + ($cantidadTotal * $precioUnitarioBase)) / $totalUnidadesNuevas;
                }else{
                    $nuevoCpp = $precioUnitarioBase;
                }
                //Calcular precios de ventas
                $precioDetalle = $nuevoCpp * (1 + $detalle['margen_detalle'] / 100);
                $precioMayor = $nuevoCpp * (1 + $detalle['margen_mayor'] / 100);

                $producto->update([
                    'precio_detalle' => $precioDetalle,
                    'precio_mayor' => $precioMayor,
                    'costo_promedio' => $nuevoCpp
                ]);

                //Calculamos el subtotal y acumulamos el total de la compra
                $cantidadFactura = $cantidadTotal / $factorConversion;
                $subTotal = $cantidadFactura * $detalle['precio_unitario'];
                $totalCompra += $subTotal;

                //Guardamos los detalles de las compras
                DetalleCompra::create([
                    'cantidad' => $cantidadFactura,
                    'factor_conversion' => $factorConversion,
                    'precio_unitario' => $detalle['precio_unitario'],
                    'margen_detalle' => $detalle['margen_detalle'],
                    'margen_mayor' => $detalle['margen_mayor'],
                    'subtotal' => $subTotal,
                    'compra_id' => $compra->id,
                    'producto_id' => $producto->id
                ]);
                //Incremnetamos el stock
                $producto->increment('stock', $cantidadTotal);
            }

            //Actualizamos el total de la conpra
            $compra->update(['total' => $totalCompra]);

            //Confirmamos la transacion
            DB::commit();
            return response()->json([
                'message' => 'Compra registrada correctamente.',
                'compra' => $compra->load(['detalleCompras.producto', 'lotes'])
            ], 201);

        }catch(\Exception $e){
            //Si falla algo revertimos
            DB::rollBack();

            return response()->json([
                'message' => 'Error interno en el servidor.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try{
            //Obtenemos lo que tiene la compra relacionado
            $compra = Compra::with(['user', 'detalleCompras.producto', 'proveedor', 'lotes'])->findOrFail($id);
            return response()->json($compra, 200);

        }catch(ModelNotFoundException $e){
            return response()->json([
                'message' => 'No se ha encontrado la compra.'
            ], 404);

        }catch(\Exception $e){
            return response()->json([
                'message' => 'Error interno en el servidor.'
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
       //
    }

    //Funcion para anular una compra
    public function anular(string $id)
    {
        try{
            //Buscamos la compra con sus relaciones
            $compra = Compra::with([
                'detalleCompras',
                'detalleCompras.producto',
                'detalleCompras.producto.lotes'
            ])->findOrFail($id);

            //Validamos que no este anulada la compra
            if($compra->estado === 'ANULADA'){
                return response()->json([
                    'message' => 'Esta compra ya fue anulada anteriormente.'
                ], 409);
            }

            //Validaciones previas para asegurar que sea seguro revertir
            foreach($compra->detalleCompras as $detalle){
                $producto = $detalle->producto;

                //Si el producto es perecedero verificar el lote correspondiente no se haya vendido
                if($producto->perecedero === 'PERECEDERO'){
                    //Buscamos el lote creado en esta compra
                    $lotes = Lote::where('producto_id', $producto->id)
                                ->where('compra_id', $compra->id)
                                ->get();

                    if($lotes->isEmpty()){
                        return response()->json([
                            'message' => "No se encontró el lote correspondiente para el producto '{$producto->nombre}'."
                        ], 409);
                    }
                    foreach($lotes as $lote){
                        if ($lote->cantidad_actual < $lote->cantidad_inicial) {
                            return response()->json([
                                'message' => "No se puede anular. El lote '{$lote->codigo_lote}' del producto '{$producto->nombre}' ya fue parcialmente vendido."
                            ], 409);
                        }
                    }
                }else{
                    //Para NORMAL solo verificamos que el stock no quede negativo
                    $unidades = $detalle->cantidad * $detalle->factor_conversion;
                    if ($producto->stock - $unidades < 0) {
                        return response()->json([
                            'message' => "No se puede anular la compra. El producto '{$producto->nombre}' no tiene suficiente stock para revertir esta operación."
                        ], 409);
                    }
                }
            }
            //Iniciamos la transacion para revertir
            DB::beginTransaction();
            try{
                foreach($compra->detalleCompras as $detalle){
                    $producto = $detalle->producto;

                    //Revertimos el stock
                    $unidades = $detalle->cantidad * $detalle->factor_conversion;
                    //Calcular CPP inverso antes de anular
                    $stockActual = $producto->stock;
                    $stockOriginal = $stockActual - $unidades;
                    $precioUnitarioBase = $detalle->precio_unitario / $detalle->factor_conversion;

                    if($stockOriginal > 0){
                        $nuevoCpp = (($stockActual * $producto->costo_promedio) - ($unidades * $precioUnitarioBase)) / $stockOriginal;
                    }else{
                        $nuevoCpp = 0;
                    }

                    //Revertimos los precios de ventas y margenes de detalle
                    $precioDetalle = $nuevoCpp * (1 + $detalle->margen_detalle / 100);
                    $precioMayor = $nuevoCpp * (1 + $detalle->margen_mayor / 100);

                    $producto->decrement('stock', $unidades);
                    $producto->update([
                        'costo_promedio' => max(0, $nuevoCpp),
                        'precio_detalle' => max(0, $precioDetalle),
                        'precio_mayor' => max(0, $precioMayor)
                    ]);

                    if($producto->perecedero === 'PERECEDERO'){
                        //Actualizamos solo los lotes que pertenecen a esa compra
                        Lote::where('producto_id', $producto->id)
                            ->where('compra_id', $compra->id)
                            ->update([
                                'estado' => 'INACTIVO',
                                'motivo_inactivo' => 'ANULACION'
                            ]);
                    }
                }
                //Marcamos la compra como anulada
                $compra->update(['estado' => 'ANULADA']);

                DB::commit();
                return response()->json([
                    'message' => 'Compra anulada correctamente.'
                ], 200);

            }catch(\Exception $e){
                //Si hubo un error se revirte la anulacion
                DB::rollBack();
                return response()->json([
                    'message' => 'Error al anular la compra.',
                    'error' => $e->getMessage()
                ], 500);
            }

        }catch(ModelNotFoundException $e){
            return response()->json([
                'message' => 'Compra no encontrada.'
            ], 404);
        }catch(\Exception $e){
            return response()->json([
                'message' => 'Error interno en el servidor.'
            ], 500);
        }
    }
}
