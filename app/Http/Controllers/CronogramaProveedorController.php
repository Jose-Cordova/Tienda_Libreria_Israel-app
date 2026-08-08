<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CronogramaProveedor;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\Rule;

class CronogramaProveedorController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try{
            //Validamos los parametros
            $validator = Validator::make($request->all(), [
                'fecha_inicio' => 'nullable|date',
                'fecha_fin' => 'nullable|date|after_or_equal:fecha_inicio',
                'proveedor_id' => 'nullable|exists:proveedores,id'
            ]);

            //Si falla la validacion retornamos el error
            if($validator->fails()){
                return response()->json([
                    'message' => 'Error de validacion',
                    'errors' => $validator->errors()
                ], 422);
            }

            //Construimos la consulta base
            $query = CronogramaProveedor::with('proveedor');
            //Aplicamos filtros si vienen en la peticion
            if($request->filled('fecha_inicio')){
                $query->whereDate('fecha', '>=', $request->fecha_inicio);
            }
            if($request->filled('fecha_fin')){
                $query->whereDate('fecha', '<=', $request->fecha_fin);
            }
            if($request->filled('proveedor_id')){
                $query->where('proveedor_id', $request->proveedor_id);
            }

            //Ordenamos por fecha y obtenemos los resultados
            $eventos = $query->orderBy('fecha', 'asc')->get();
            return response()->json($eventos, 200);

        }catch(\Exception $e){
            return response()->json([
                'message' => 'Error al obtener los eventos del cronograma.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try{
            //Validamos los datos del formulario
            $validator = Validator::make($request->all(), [
                'proveedor_id' => 'required|exists:proveedores,id',
                'fecha' => 'required|date|after_or_equal:today',
                'contenido' => 'required|string|max:255'
            ]);

            //Si falla la validacion retornamos el error
            if($validator->fails()){
                return response()->json([
                    'message' => 'Error de validacion',
                    'errors' => $validator->errors()
                ], 422);
            }

            //Creamos el cronograma
            $evento = CronogramaProveedor::create([
                'proveedor_id' => $request->proveedor_id,
                'fecha' => $request->fecha,
                'contenido' => $request->contenido
            ]);
            //Cargamos la relacion de proveedor
            $evento->load('proveedor');

            return response()->json([
                'message' => 'Evento creado correctamente.',
                'evento' => $evento
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
            //Buscamos el evento con su relacion con proveedor
            $evento = CronogramaProveedor::with('proveedor')->findOrFail($id);

            return response()->json($evento, 200);

        }catch(ModelNotFoundException $e){
            return response()->json([
                'message' => 'Evento no encontrado.'
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
        try{
            //Buscamos el evento por id
            $evento = CronogramaProveedor::findOrFail($id);
            //Validamos los datos recibidos
            $validator = Validator::make($request->all(), [
                'proveedor_id' => 'sometimes|exists:proveedores,id',
                'fecha' => 'sometimes|date|after_or_equal:today',
                'contenido' => 'sometimes|string|max:255'
            ]);

            //Si falla la validacion retornamos el error
            if($validator->fails()){
                return response()->json([
                    'message' => 'Error de validacion',
                    'errors' => $validator->errors()
                ], 422);
            }

            //Actualizamos los campos que vienen en la peticion
            $evento->fill($request->only(['proveedor_id', 'fecha', 'contenido']));
            $evento->save();
            $evento->load('proveedor');

            return response()->json([
                'message' => 'Evento actualizado correctamente.',
                'evento' => $evento
            ], 200);

        }catch(ModelNotFoundException $e){
            return response()->json([
                'message' => 'Evento no encontrado.'
            ], 404);

        }catch(\Exception $e){
            return response()->json([
                'message' => 'Error interno en el servidor.',
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
            //Buscamos el evento
            $evento = CronogramaProveedor::findOrFail($id);

            $evento->delete();
            return response()->json([
                'message' => 'Evento eliminado correctamente.'
            ], 200);

        }catch(ModelNotFoundException $e){
            return response()->json([
                'message' => 'Evento no encontrado.'
            ], 404);

        }catch(\Exception $e){
            return response()->json([
                'message' => 'Error interno en el servidor',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
