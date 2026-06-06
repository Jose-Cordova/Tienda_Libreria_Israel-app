<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Models\Marca;
//Validaciones
use App\Http\Requests\CMMPRequest;

class MarcaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $query = Marca::query();

            // Búsqueda por nombre
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where('nombre', 'ilike', "%{$search}%");
            }

            $query->orderBy('id', 'asc');
            $perPage = $request->query('per_page', 5);
            $marcas = $query->paginate($perPage);

            return response()->json($marcas, 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener las marcas.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CMMPRequest $request)
    {
        try{
            //Se retornan los datos que pasaron la validacion y se crea el registro
            $marca = Marca::create($request->validated());

            return response()->json([
                'message' => 'Marca creada correctamente.',
                'marca' => $marca
            ], 200);

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
            $marca = Marca::findOrFail($id);
            return response()->json($marca, 200);

        }catch(ModelNotFoundException $e){
            return response()->json([
                'message' => 'Marca no encontrada.'
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
    public function update(CMMPRequest $request, string $id)
    {
        try{
            $marca = Marca::findOrFail($id);
            //Se retornan los datos que pasaron la validacion y se actualiza el registro
            $marca->update($request->validated());

            return response()->json([
                'message' => 'Marca actualizada correctamente.',
                'marca' => $marca
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
            $marca = Marca::with('productos')->findOrFail($id);
            if($marca->productos()->exists()){
                return response()->json([
                    'message' => 'No se puede eliminar la marca porque tiene productos asociados.'
                ], 409);
            }
            $marca->delete();

            return response()->json([
                'message' => 'Marca eliminada correctamente.'
            ], 200);

        }catch(ModelNotFoundException $e){
            return response()->json([
                'message' => 'Marca no encontrada.'
            ], 404);

        }catch(\Exception $e){
            return response()->json([
                'message' => 'Error interno en el servidor.'
            ], 500);
        }
    }
}
