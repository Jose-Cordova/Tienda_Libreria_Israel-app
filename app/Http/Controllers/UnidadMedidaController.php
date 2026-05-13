<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Models\UnidadMedida;
//Validaciones
use App\Http\Requests\UMRequest;

class UnidadMedidaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try{
            $unidadesmedidas = UnidadMedida::orderBy('id', 'desc')->get();
            return response()->json($unidadesmedidas, 200);

        }catch(\Exception $e){
            return response()->json([
                'message' => 'Error al obtener las unidades de medida.'
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(UMRequest $request)
    {
        try{
            //Se retornan los datos que pasaron la validacion y se crea el registro
            $unidadmedida = UnidadMedida::create($request->validated());

            return response()->json([
                'message' => 'unidad de medida creada correctamente.',
                'unidadmedida' => $unidadmedida
            ], 201);

        }catch(\Exception $e){
            return response()->json([
                'message' => 'Error interno en el servidor.'
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try{
            $unidadmedida = UnidadMedida::findOrFail($id);
            return response()->json($unidadmedida, 200);

        }catch(ModelNotFoundException $e){
            return response()->json([
                'message' => 'La unidad de media nose encontro .'
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
    public function update(UMRequest $request, string $id)
    {
        try{
            $unidadmedida = UnidadMedida::findOrFail($id);
            //Se retornan los datos que pasaron la validacion y se actualiza el registro
            $unidadmedida->update($request->validated());

            return response()->json([
                'message' => 'Unidad de medida actualizada correctamente.',
                'unidadmedida' => $unidadmedida
            ], 200);

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
            $unidadmedida = UnidadMedida::with('productos')->findOrFail($id);
            if($unidadmedida->productos()->exists()){
                return response()->json([
                    'message' => 'No se puede eliminar la unidad de medida porque tiene productos asociados.'
                ], 409);
            }
            $unidadmedida->delete();

            return response()->json([
                'message' => 'Unidad de medida eliminada correctamente.'
            ], 200);

        }catch(ModelNotFoundException $e){
            return response()->json([
                'message' => 'Unidada de medida no encontrada.'
            ], 404);

        }catch(\Exception $e){
            return response()->json([
                'message' => 'Error interno en el servidor.'
            ], 500);
        }
    }
}
