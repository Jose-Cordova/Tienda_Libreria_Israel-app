<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Models\MetodoPago;

class MetodoPagoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try{
            $metodosPagos = MetodoPago::orderBy('id','desc')->get();
            return response()->json($metodosPagos, 200);
            }
        catch(\Exception $e){
            return response()->json([
                'message' => 'Error al obtener los metodos de pagos.'
            ],500);
        }
    }
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
         try {

            $request->validate(
                [
                    'nombre' => [
                        'required',
                        'string',
                        'min:3',
                        'max:90',
                        'unique:metodos_pagos,nombre',
                        'regex:/^[\pL\s]+$/u'
                    ]
                ],
                [
                    'nombre.required' => 'El nombre del método de pago es obligatorio.',
                    'nombre.string'   => 'El nombre debe ser texto.',
                    'nombre.min'      => 'El nombre debe tener al menos 3 caracteres.',
                    'nombre.max'      => 'El nombre no puede superar los 90 caracteres.',
                    'nombre.unique'   => 'Ya existe un método de pago con ese nombre.',
                    'nombre.regex'    => 'El nombre solo puede contener letras y espacios.'
                ]
            );

            // Crear método de pago
            $metodoPago = MetodoPago::create([
                'nombre' => $request->nombre
            ]);

            return response()->json([
                'message' => 'Método de pago creado correctamente.',
                'metodoPago' => $metodoPago
            ], 201);

        } catch (ValidationException $e) {

            return response()->json([
                'message' => 'Error de validación.',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {

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
            $metodoPago = MetodoPago::findOrFail($id);
            return response()->json($metodoPago, 200);
        }
        catch(ModelNotFoundException $e){
            return response()->json([
                'message' => 'Metodo de pago no encontrado.'
            ],404);
        }
        catch(\Exception $e){
            return response()->json([
                'message' => 'Error al obtener el método de pago.'
            ],500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
          try{

            $metodoPago = MetodoPago::findOrFail($id);

            $request->validate(
                [
                    'nombre' => [
                        'required',
                        'string',
                        'min:3',
                        'max:90',
                        Rule::unique('metodos_pagos', 'nombre')->ignore($id),
                        'regex:/^[\pL\s]+$/u'
                    ]
                ],
                [
                    'nombre.required' => 'El nombre del método de pago es obligatorio.',
                    'nombre.string'   => 'El nombre debe ser texto.',
                    'nombre.min'      => 'El nombre debe tener al menos 3 caracteres.',
                    'nombre.max'      => 'El nombre no puede superar los 90 caracteres.',
                    'nombre.unique'   => 'Ya existe un método de pago con ese nombre.',
                    'nombre.regex'    => 'El nombre solo puede contener letras y espacios.'
                ]
            );
            $metodoPago->update([
                'nombre' => $request->nombre
            ]);
            return response()->json([
                'message' => 'Metodo de pago actualizado correctamente.',
                'metodoPago' => $metodoPago
            ],200);
        }

        catch(ValidationException $e){
            return response()->json([
                'message' => 'Error de validacion.',
                'errors' => $e->errors()
            ],422);
        }
        catch(ModelNotFoundException $e){
            return response()->json([
                'message' => 'Metodo de pago no encontrado.'
            ],404);
        }
        catch(\Exception $e){
            return response()->json([
                'message' => 'Error interno en el servidor'
            ],500);
        }

    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $metodoPago = MetodoPago::findOrFail($id);


            if ($metodoPago->ventas()->exists()) {
                return response()->json([
                    'message' => 'No se puede eliminar el método de pago porque tiene ventas asociadas.'
                ], 409);
            }


            if ($metodoPago->abonos()->exists()) {
                return response()->json([
                    'message' => 'No se puede eliminar el método de pago porque tiene abonos asociados.'
                ], 409);
            }

            $metodoPago->delete();

            return response()->json([
                'message' => 'Método de pago eliminado correctamente.'
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Método de pago no encontrado.'
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error interno en el servidor.'
            ], 500);
        }
    }
}



