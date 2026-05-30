<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Models\Categoria;
//Validaciones
use App\Http\Requests\CMMPRequest;


class CategoriaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try{
            $query = Categoria::query();

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where('nombre', 'ilike', "%{$search}%");
            }
            $query->orderBy('id', 'asc');

            $perPage = $request->query('per_page', 5);
            $categorias = $query->paginate($perPage);

            return response()->json($categorias, 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener las categorías.',
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
            $categoria = Categoria::create($request->validated());

            return response()->json([
                'message' => 'Categoria creada correctamente.',
                'categoria' => $categoria
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
            $categoria = Categoria::findOrFail($id);
            return response()->json($categoria, 200);

        }catch(ModelNotFoundException $e){
            return response()->json([
                'message' => 'Categoria no encontrada.'
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
            $categoria = Categoria::findOrFail($id);
            //Se retornan los datos que pasaron la validacion y se actualiza el registro
            $categoria->update($request->validated());

            return response()->json([
                'message' => 'Categoria actualizada correctamente.',
                'categoria' => $categoria
            ], 200);

        }catch(ModelNotFoundException $e){
            return response()->json([
                'message' => 'Categoria no encontrada.'
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
            $categoria = Categoria::with('productos')->findOrFail($id);
            if($categoria->productos()->exists()){
                return response()->json([
                    'message' => 'No se puede eliminar la categoría porque tiene productos asociados.'
                ], 409);
            }
            $categoria->delete();

            return response()->json([
                'message' => 'Categoria eliminada correctamente.'
            ], 200);

        }catch(ModelNotFoundException $e){
            return response()->json([
                'message' => 'Categoria no encontrada.'
            ], 404);

        }catch(\Exception $e){
            return response()->json([
                'message' => 'Error interno en el servidor.'
            ], 500);
        }
    }
}
