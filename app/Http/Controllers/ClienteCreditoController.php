<?php

namespace App\Http\Controllers;

use App\Models\ClienteCredito;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use App\Http\Requests\UpdateClienteCreditoRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;


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


    public function update(UpdateClienteCreditoRequest $request, string $id)
    {
        try {
            $cliente = ClienteCredito::findOrFail($id);
            $data = $request->validated();

            $cliente->update([
                'nombre'   => $data['nombre'],
                'dui'      => $data['dui'] ?? null,
                'telefono' => $data['telefono'] ?? null,
            ]);

            return response()->json([
                'message' => 'Cliente de crédito actualizado correctamente.',
                'cliente' => $cliente,
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación.',
                'errors'  => $e->errors(),
            ], 422);

        } catch (QueryException $e) {
            // Capturar errores de unicidad
            if (str_contains($e->getMessage(), 'clientes_creditos_dui_unique')) {
                return response()->json([
                    'message' => 'El DUI ya está registrado en otro cliente.',
                ], 422);
            }
            if (str_contains($e->getMessage(), 'clientes_creditos_telefono_unique')) {
                return response()->json([
                    'message' => 'El teléfono ya está registrado en otro cliente.',
                ], 422);
            }
            return response()->json([
                'message' => 'Error en la base de datos.',
                'error'   => $e->getMessage(),
            ], 500);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Cliente de crédito no encontrado.',
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error interno en el servidor.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
