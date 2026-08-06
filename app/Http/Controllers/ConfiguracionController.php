<?php

namespace App\Http\Controllers;

use App\Models\Configuracion;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;



class ConfiguracionController extends Controller
{
    /**
     * Mostrar la configuración actual de la tienda.
     */
    public function index()
    {
        $config = Configuracion::firstOrCreate([]);
        return response()->json($config);
    }

    /**
     * Actualizar la configuración de la tienda.
     */
    public function update(Request $request)
{
    try {
        $data = $request->validate([
            'nombre_tienda' => 'required|string|max:100',
            'telefono'      => 'required|string|max:20|min:9',
            'email'         => 'required|email|max:100',
        ], [
            'nombre_tienda.required' => 'El nombre de la tienda es obligatorio.',
            'nombre_tienda.max'      => 'El nombre no debe exceder los 100 caracteres.',
            'telefono.required'      => 'El número de teléfono es obligatorio.',
            'telefono.max'           => 'El teléfono no debe exceder los 20 caracteres.',
            'telefono.min'           => 'El teléfono no debe tener menos 9 caracteres.',
            'email.required'         => 'El correo electrónico es obligatorio.',
            'email.email'            => 'El correo electrónico debe ser válido.',
            'email.max'              => 'El correo no debe exceder los 100 caracteres.',
        ]);

        $config = Configuracion::firstOrCreate([]);
        $config->update($data);

        return response()->json([
            'message'       => 'Configuración actualizada correctamente.',
            'configuracion' => $config
        ]);
    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'message' => 'Error de validación.',
            'errors'  => $e->errors()
        ], 422);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Error al actualizar la configuración.',
            'error'   => $e->getMessage()
        ], 500);
    }
}
}
