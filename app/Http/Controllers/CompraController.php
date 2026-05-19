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
            //Obtenemos todo lo relacionado con compras
            $query = Compra::with(['user', 'detalleCompras', 'proveedor']);
            //Filtro por estados
            if($request->estado){
                $query->where('estado', $request->estado);
            }
            //Filtro por el nombre del proveedor
            if($request->proveedor_id){
                $query->where('proveedor_id', $request->proveedor_id);
            }
            $compras = $query->orderBy('id', 'desc')->get();

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
                //Si el producto es nuevo lo creamos
                if(is_null($detalle['producto_id'])){
                    $producto = Producto::create([
                        'nombre' => $detalle['nombre'],
                        'precio_detalle' => 0,
                        'precio_mayor' => 0,
                        'stock' => 0,
                        'stock_minimo' => $detalle['stock_minimo'],
                        'perecedero' => $detalle['perecedero'],
                        'estado' => 'ACTIVO',
                        'marca_id' => $detalle['marca_id'],
                        'categoria_id' => $detalle['categoria_id'],
                        'unidad_medida_id' => $detalle['unidad_medida_id']
                    ]);
                    $esProductoNuevo = true;
                }else{
                    //Si el producto existe lo buscamos
                    $producto = Producto::findOrFail($detalle['producto_id']);
                    $esProductoNuevo = false;

                    //Si el prod esta inactivo por una anulacion lo reactivamos
                    if($producto->estado === 'INACTIVO'){
                        $producto->update(['estado' => 'ACTIVO']);
                    }
                }

                //Si el producto es perecedero se crea el lote correspondiente
                if($this->esPerecedero($producto, $detalle)){
                    Lote::create([
                        'codigo_lote' => $detalle['codigo_lote'],
                        'fecha_vencimiento' => $detalle['fecha_vencimiento'],
                        'fecha_ingreso' => now(),
                        'cantidad_inicial' => $detalle['cantidad'],
                        'cantidad_actual' => $detalle['cantidad'],
                        'estado' => 'ACTIVO',
                        'producto_id' => $producto->id
                    ]);
                }

                //Calculamos los precios de venta al detalle y al mayor y actualizamos
                $precioDetalle = $detalle['precio_unitario'] * (1 + $detalle['margen_detalle'] / 100);
                $precioMayor = $detalle['precio_unitario'] * (1 + $detalle['margen_mayor'] / 100);

                $producto->update([
                    'precio_detalle' => $precioDetalle,
                    'precio_mayor' => $precioMayor
                ]);

                //Calculamos el suubtotal y acumulamos el total de la compra
                $subTotal = $detalle['cantidad'] * $detalle['precio_unitario'];
                $totalCompra += $subTotal;

                //Guardamos los detalles de las compras
                DetalleCompra::create([
                    'cantidad' => $detalle['cantidad'],
                    'precio_unitario' => $detalle['precio_unitario'],
                    'margen_detalle' => $detalle['margen_detalle'],
                    'margen_mayor' => $detalle['margen_mayor'],
                    'subtotal' => $subTotal,
                    'es_producto_nuevo' => $esProductoNuevo,
                    'compra_id' => $compra->id,
                    'producto_id' => $producto->id
                ]);
                //Incremnetamos el stock
                $producto->increment('stock', $detalle['cantidad']);
            }

            //Actualizamos el total de la conpra
            $compra->update(['total' => $totalCompra]);

            //Confirmamos la transacion
            DB::commit();
            return response()->json([
                'message' => 'Compra registrada correctamente.',
                'compra' => $compra->load('detalleCompras.producto')
            ], 201);

        }catch(\Exception $e){
            //Si falla algo revertimos
            DB::rollBack();

            return response()->json([
                'message' => 'Error interno en el servidor.',
                'ERROR' => $e->getMessage()
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
            $compra = Compra::with(['user', 'detalleCompras', 'proveedor'])->findOrFail($id);
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

    //Funcion para determinar si un producto es perecedero
    private function esPerecedero($producto, array $detalle): bool
    {
        //Para producto nuevo y existente
        if(is_null($detalle['producto_id'] ?? null)){
            return ($detalle['perecedero'] ?? '') === 'PERECEDERO';
        }
        return $producto->perecedero === 'PERECEDERO';
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
            //Validamos que la compra sea del dia actual
            if($compra->fecha_registro->toDateString() != now()->toDateString()){
                return response()->json([
                    'message' => 'No se puede anular una compra de días anteriores. Utilice una devolución de compra.'
                ], 409);
            }

            //Validaciones previas para asegurar que sea seguro revertir
            foreach($compra->detalleCompras as $detalle){
                $producto = $detalle->producto;

                //Verificamos que el stock no quede negativo
                if($producto->stock - $detalle->cantidad < 0){
                    return response()->json([
                        'message' => "No se puede anular la compra. El producto '{$producto->nombre}' tiene ventas posteriores y su stock quedaría negativo."
                    ], 409);
                }
                //Si el producto es perecedero verificar el lote correspondiente no se haya vendido
                if($producto->perecedero === 'PERECEDERO'){
                    //Buscamos el lote creado en esta compra
                    $lote = $producto->lotes->first(function($lote) use ($detalle){
                        return $lote->fecha_ingreso->toDateString() === now()->toDateString() && $lote->cantidad_inicial == $detalle->cantidad;
                    });
                    if(!$lote){
                        return response()->json([
                            'message' => "No se encontró el lote correspondiente para el producto '{$producto->nombre}'."
                        ], 409);
                    }
                    //Verificamos si se a vendido algo del lote
                    if($lote->cantidad_actual < $lote->cantidad_inicial){
                        return response()->json([
                            'message' => "No se puede anular la compra. El lote del producto '{$producto->nombre}' ya fue parcialmente vendido."
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
                    $producto->decrement('stock', $detalle->cantidad);

                    //Si es perecedero eliminamos el lote
                    if($producto->perecedero === 'PERECEDERO'){
                        $lote = $producto->lotes->first(function ($lote) use ($detalle){
                            return $lote->fecha_ingreso->toDateString() === now()->toDateString() && $lote->cantidad_inicial == $detalle->cantidad;
                        });
                        if($lote){
                            $lote->delete();
                        }
                    }

                    //Si el producto fue creado en esta compra lo inactivamos
                    if($detalle->es_producto_nuevo){
                        $otrasCompras = DetalleCompra::where('producto_id', $producto->id)
                            ->where('compra_id', '!=', $compra->id)
                            ->exists();
                        if(!$otrasCompras){
                            $producto->update(['estado' => 'INACTIVO']);
                        }
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
