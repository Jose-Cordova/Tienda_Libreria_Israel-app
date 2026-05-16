<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Models\Proveedor;

//validacion
use App\Http\Requests\ProveedorRequest;

class ProveedorController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try{
            $proveedores = Proveedor::orderBy('id', 'desc')->get();
            return response()->json($proveedores, 200);

        }catch(\Exception $e){
            return response()->json([
                'message' => 'Error al obtener los proveedores.'
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ProveedorRequest $request)
    {
        try{
            //Se retornan los datos que pasaron la validacion y se crea el registro
            $proveedor = Proveedor::create($request->validated());

            return response()->json([
                'message' => 'Proveedor creado correctamente.',
                'proveedor' => $proveedor
            ], 200);

        }catch(\Exception $e){
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
            $proveedor = Proveedor::findOrfail($id);
            return response()->json($proveedor, 200);

        }catch(ModelNotFoundException $e){
            return response()->json([
                'message' => 'Proveedor no encontrado.'
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
    public function update(ProveedorRequest $request, string $id)
    {
        try{
            $proveedor = Proveedor::findOrFail($id);
            //Se retornan los datos que pasaron la validacion y se actualiza el registro
            $proveedor->update($request->validated());

            return response()->json([
                'message' => 'Proveedor actualizado correctamente.',
                'proveedor' => $proveedor
            ], 200);

        }catch(ModelNotFoundException $e){
            return response()->json([
                'message' => 'Proveedor no encontrado.'
            ], 404);

        }catch(\Exception $e){
            return response()->json([
                'message' => 'Error interno en el servidor.'
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try{
            $proveedor = Proveedor::with('compras', 'cronogramaProveedores')->findOrFail($id);

            if($proveedor->compras()->exists()){
                return response()->json([
                    'message' => 'No se puede eliminar el proveedor por que tiene compras asociadas.'
                ], 409);
            }elseif($proveedor->cronogramaProveedores()->exists()){
                return response()->json([
                    'message' => 'No se puede eliminar el proveedor por que esta asociado al cronograma.'
                ], 409);
            }

            $proveedor->delete();

            return response()->json([
                'message' => 'Proveedor eliminado correctamente.'
            ], 200);

        }catch(ModelNotFoundException $e){
            return response()->json([
                'message' => 'Proveedor no encontrado.'
            ], 404);

        }catch(\Exception $e){
            return response()->json([
                'message' => 'Error interno en el servidor.'
            ], 500);
        }
    }
}
