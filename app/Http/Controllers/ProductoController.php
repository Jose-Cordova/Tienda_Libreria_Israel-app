<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\ProductoRequest;
use App\Models\Producto;
use Illuminate\Database\eloquent\ModelNotFoundException;

class ProductoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
      try {
        $productos = Prodcutos::orderBy('id', 'desc')->get();
        return response()->json($productos,200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener los productos.'
            ], 500);
        }

    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ProductoRequest $request)
{
    try {
        DB::beginTransaction();

        $producto = Producto::create([
            'nombre'           => $request->nombre,
            'precio_detalle'   => $request->precio_detalle,
            'precio_mayor'     => $request->precio_mayor,
            'stock'            => $request->cantidad_inicial,
            'stock_minimo'     => $request->stock_minimo,
            'perecedero'       => $request->perecedero,
            'estado'           => 'ACTIVO',
            'unidad_medida_id' => $request->unidad_medida_id,
            'marca_id'         => $request->marca_id,
            'categoria_id'     => $request->categoria_id
        ]);

        Lote::create([
            'fecha_vencimiento' => $request->fecha_vencimiento,
            'codigo_lote'       => $request->codigo_lote,
            'fecha_ingreso'     => now(),
            'cantidad_inicial'  => $request->cantidad_inicial,
            'cantidad_actual'   => $request->cantidad_inicial,
            'estado'            => 'ACTIVO',
            'producto_id'       => $producto->id
        ]);

        DB::commit();

        return response()->json([
            'message'  => 'Producto creado correctamente.',
            'producto' => $producto->load('lotes')
        ], 201);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'message' => 'Error interno del servidor.'
        ], 500);
    }
}



    /**
     * Display the specified resource.
     */
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
    /**
     * Update the specified resource in storage.
     */
    public function update(ProductoRequest $request, string $id)
    {
        try {
            $producto = Producto::findOrfail($id);
            $producto->update($request->validated());

            return response()->json([
                'message' => 'Producto actualizado correctamente.',
                'producto' => $producto
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Producto no encontrado.'
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error interno del servidor.'
            ], 500);
        }

    }

    public function cambiarEstado(string $id)
    {
         try {
            $producto = Producto::findOrFail($id);

            $producto->update([
                'estado' => $producto->estado === 'ACTIVO' ? 'INACTIVO' : 'ACTIVO'
            ]);
            return response()->json([
                'message' => 'Estado del producto actualizado correctamente.',
                'producto' => $producto
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json ([
                'message' => 'Producto no encontrado.'
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error interno del servidor.'
            ], 500);
        }

    }

    public function alertaStockMinimo()
    {
        try{
            $productos = Producto::where('stock', '<=', \DB::raw ('stock_minimo'))
            ->where('estado', 'ACTIVO')
            ->get()
            ->map (function ($producto){
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

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
