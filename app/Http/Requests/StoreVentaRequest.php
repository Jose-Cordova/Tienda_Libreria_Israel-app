<?php

namespace App\Http\Requests;

use App\Models\ClienteCredito;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreVentaRequest extends FormRequest
{
    public function authorize(): bool
    {
        // verificar si el usuario actual esta autorizado para hacer esta accion
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'user_id' => 'required|exists:users,id',
            'metodo_pago_id' => $this->estado === 'CREDITO'
                                    ? 'nullable|exists:metodos_pagos,id'
                                    : 'required|exists:metodos_pagos,id',
            'tipo_cliente' => 'required|in:DETALLES,MAYORISTA',
            'estado' => 'required|in:PAGADA,CREDITO',

            'monto_recibido' => 'nullable|numeric|min:0',

            'detalle' => 'required|array|min:1',
            'detalle.*.producto_id' => 'required|exists:productos,id',
            'detalle.*.cantidad' => 'required|integer|min:1',

            'cliente_credito_id' => 'nullable|exists:clientes_creditos,id',
            'nombre' => 'nullable|string|max:50',
            'dui' => 'nullable|string|max:10|unique:clientes_creditos,dui',
            'telefono' => 'nullable|string|max:20|unique:clientes_creditos,telefono',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Solo aplica cuando se está creando un cliente nuevo
            if ($this->filled('cliente_credito_id')) {
                return;
            }

            // Solo aplica si viene nombre (venta a crédito con cliente nuevo)
            if (! $this->filled('nombre')) {
                return;
            }

            $nombreNormalizado = $this->normalizarTexto($this->nombre);
            $telefono = $this->filled('telefono') ? trim($this->telefono) : null;

            // Recorremos los clientes existentes y normalizamos para comparar
            $clientes = ClienteCredito::all(['nombre', 'telefono']);

            $duplicado = $clientes->first(function ($cliente) use ($nombreNormalizado, $telefono) {
                $nombreDb = $this->normalizarTexto($cliente->nombre);

                if ($nombreDb !== $nombreNormalizado) {
                    return false;
                }

                // Si se envió teléfono, comparar también el teléfono
                if ($telefono) {
                    return trim($cliente->telefono) === $telefono;
                }

                return true;
            });

            if ($duplicado) {
                $mensaje = $telefono
                    ? 'Ya existe un cliente con ese mismo nombre y número de teléfono. Verifique antes de crear uno nuevo.'
                    : 'Ya existe un cliente con ese mismo nombre. Verifique antes de crear uno nuevo.';

                $validator->errors()->add('nombre', $mensaje);
            }
        });
    }

    /**
     * Normaliza un texto para comparación: minúsculas, sin acentos, sin espacios ni símbolos.
     */
    private function normalizarTexto($texto)
    {
        $texto = Str::ascii(mb_strtolower(trim($texto), 'UTF-8'));

        return preg_replace('/[^a-z0-9]/', '', $texto);
    }
}
