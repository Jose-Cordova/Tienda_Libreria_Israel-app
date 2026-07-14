<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVentaRequest extends FormRequest
{
    public function authorize(): bool
    {
        //Verificar si el usuario actual esta autorizado para hacer esta accion
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'user_id'           => 'required|exists:users,id',
            'metodo_pago_id'    => $this->estado === 'CREDITO'
                                    ? 'nullable|exists:metodos_pagos,id'
                                    : 'required|exists:metodos_pagos,id',
            'tipo_cliente'      => 'required|in:DETALLES,MAYORISTA',
            'estado'            => 'required|in:PAGADA,CREDITO',

            'monto_recibido'    => 'nullable|numeric|min:0',

            'detalle'                    => 'required|array|min:1',
            'detalle.*.producto_id'      => 'required|exists:productos,id',
            'detalle.*.cantidad'         => 'required|integer|min:1',

            'cliente_credito_id' => 'nullable|exists:clientes_creditos,id',
            'nombre'             => 'nullable|string|max:50',
            'dui'                => 'nullable|string|max:10|unique:clientes_creditos,dui',
            'telefono'           => 'nullable|string|max:20|unique:clientes_creditos,telefono',
        ];
    }
}
