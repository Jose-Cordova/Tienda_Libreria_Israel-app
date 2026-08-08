<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Nota;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class NotaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try{
            $query = Nota::where('user_id', auth()->id());

            //Ordenamos la notas por fecha
            $orderBy = $request->get('order_by', 'fecha');
            $orderDir = $request->get('order_dir', 'desc');
            $query->orderBy($orderBy, $orderDir);

            $perPage = $request->get('per_page', 10);
            $notas = $query->paginate($perPage);

            return response()->json($notas, 200);

        }catch(\Exception $e){
            return response()->json([
                'message' => 'Error al obtener las notas.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //Validamos que todo venga bien
        $request->validate([
            'contenido' => 'required|string|min:3',
            'fecha' => 'nullable|date'
        ],
        [
            'contenido.required' => 'El contenido es obligatorio.',
            'contenido.min' => 'El contenido debe tener al menos 3 caracteres.',
            'fecha.date' => 'La fecha no es válida.'
        ]);

        try{
            $nota = Nota::create([
                'user_id' => auth()->id(),
                'contenido' => $request->contenido,
                'fecha' => $request->fecha ?? now()
            ]);

            return response()->json([
                'message' => 'Nota creada correctamente.',
                'nota' => $nota
            ], 201);

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
            $nota = Nota::where('user_id', auth()->id())->findOrFail($id);

            return response()->json($nota, 200);

        }catch(ModelNotFoundException $e){
            return response()->json([
                'message' => 'Nota no encontrada.'
            ], 404);

        }catch(\Exception $e){
            return response()->json([
                'message' => 'Error interno en el servidor.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //Validamos que todo venga bien
        $request->validate([
            'contenido' => 'required|string|min:3',
            'fecha' => 'nullable|date'
        ],
        [
            'contenido.required' => 'El contenido es obligatorio.',
            'contenido.min' => 'El contenido debe tener al menos 3 caracteres.',
            'fecha.date' => 'La fecha no es válida.'
        ]);

        try{
            $nota = Nota::where('user_id', auth()->id())->findOrFail($id);

            $nota->update([
                'contenido' => $request->contenido,
                'fecha' => $request->fecha ?? $nota->fecha
            ]);

            return response()->json([
                'message' => 'Nota actualizada correctamente.',
                'nota' => $nota
            ], 200);

        }catch(ModelNotFoundException $e){
            return response()->json([
                'message' => 'Nota no encontrada.'
            ], 404);

        }catch(\Exception $e){
            return response()->json([
                'message' => 'Error interno en el servidor',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try{
            $nota = Nota::where('user_id', auth()->id())->findOrFail($id);
            $nota->delete();

            return response()->json([
                'message' => 'Nota eliminada correctamente.'
            ], 200);

        }catch(ModelNotFoundException $e){
            return response()->json([
                'message' => 'Nota no encontrada.'
            ], 404);

        }catch(\Exception $e){
            return response()->json([
                'message' => 'Error interno en el servidor',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
