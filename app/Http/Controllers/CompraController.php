<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Models\Compra;

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
    public function store(Request $request)
    {
        //
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
}
