<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Models\ClienteCredito;
class ClienteCreditoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try{
            $clientes = ClienteCredito::orderby('id','desc') -> get();
            return response()->json($clientes, 200);
        }
        catch(\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener los clientes con credito.'
            ], 500);
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
                    'nombre'   => 'required|string|min:3|max:50|regex:/^[\pL\s]+$/u',
                    'dui'      => 'required|string|unique:clientes_creditos,dui|regex:/^[0-9]{8}-[0-9]{1}$/',
                    'telefono' => 'required|string|min:8|max:20|unique:clientes_creditos,telefono|regex:/^[0-9\s\-]+$/'

                ],
                [
                    'nombre.required'   => 'El nombre del cliente es obligatorio.',
                    'nombre.string'     => 'El nombre debe ser texto.',
                    'nombre.min'        => 'El nombre debe tener al menos 3 caracteres.',
                    'nombre.max'        => 'El nombre no puede superar los 50 caracteres.',
                    'nombre.unique'     => 'Ya existe un cliente con ese nombre.',
                    'nombre.regex'      => 'El nombre solo puede contener letras y espacios.',


                    'dui.required'      => 'El DUI es obligatorio.',
                    'dui.unique'      => 'Ya existe un cliente con ese DUI.',
                    'dui.regex'       => 'El DUI debe tener el formato 12345678-9 o 123456789.',

                    'telefono.required' => 'El teléfono es obligatorio.',
                    'telefono.string'   => 'El teléfono debe contener solo caracteres válidos.',
                    'telefono.min'      => 'El teléfono debe tener al menos 8 caracteres.',
                    'telefono.max'      => 'El teléfono no puede superar los 20 caracteres.',
                    'telefono.unique'   => 'Ya existe un cliente con ese teléfono.',
                    'telefono.regex'    => 'El teléfono solo puede contener números, espacios y guiones.'
                ]
            );

            $cliente = ClienteCredito::create([
                'nombre'   => $request->nombre,
                'dui'      => $request->dui,
                'telefono' => $request->telefono
            ]);

            return response()->json([
                'message' => 'Cliente de crédito creado correctamente.',
                'cliente' => $cliente
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación.',
                'errors'  => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error interno en el servidor.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(string $id)
    {
        try {
            $cliente = ClienteCredito::findOrFail($id);
            return response()->json($cliente, 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Cliente de crédito no encontrado.'
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener el cliente de crédito.'
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            $cliente = ClienteCredito::findOrFail($id);
            $request->validate([
                    'nombre'   => 'required|string|min:3|max:50|regex:/^[\pL\s]+$/u',
                    'dui'      => 'required|string|unique:clientes_creditos,dui,'.$id.'|regex:/^[0-9]{8}-[0-9]{1}$/',
                    'telefono' => 'required|string|min:8|max:20|unique:clientes_creditos,telefono,'.$id.'|regex:/^[0-9\s\-]+$/'
                ],
                [
                    'nombre.required'   => 'El nombre del cliente es obligatorio.',
                    'nombre.string'     => 'El nombre debe ser texto.',
                    'nombre.min'        => 'El nombre debe tener al menos 3 caracteres.',
                    'nombre.max'        => 'El nombre no puede superar los 50 caracteres.',
                    'nombre.regex'      => 'El nombre solo puede contener letras y espacios.',

                    'dui.required'      => 'El DUI es obligatorio.',
                    'dui.unique'      => 'Ya existe un cliente con ese DUI.',
                    'dui.regex'       => 'El DUI debe tener el formato 12345678-9 o 123456789.',

                    'telefono.required' => 'El teléfono es obligatorio.',
                    'telefono.string'   => 'El teléfono debe ser texto.',
                    'telefono.min'      => 'El teléfono debe tener al menos 8 caracteres.',
                    'telefono.max'      => 'El teléfono no puede superar los 20 caracteres.',
                    'telefono.unique'   => 'Ya existe un cliente con ese teléfono.',
                    'telefono.regex'    => 'El teléfono solo puede contener números, espacios y guiones.'
                ]
            );
            $cliente->update([
                'nombre'   => $request->nombre,
                'telefono' => $request->telefono
            ]);

            return response()->json([
                'message' => 'Cliente de crédito actualizado correctamente.',
                'cliente' => $cliente
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación.',
                'errors'  => $e->errors()
            ], 422);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Cliente de crédito no encontrado.'
            ], 404);

        } catch (\Exception $e) {
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
        //
    }
}
