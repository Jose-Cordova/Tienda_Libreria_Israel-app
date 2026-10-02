<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMetodoPagoVentaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'metodo_pago_id' => 'required|exists:metodos_pagos,id',
        ];
    }

    public function messages(): array
    {
        return [
            'metodo_pago_id.required' => 'Debe seleccionar un método de pago.',
            'metodo_pago_id.exists'   => 'El método de pago seleccionado no existe.',
        ];
    }
}
