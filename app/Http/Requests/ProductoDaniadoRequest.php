<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProductoDaniadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->route()?->getActionMethod()) {
            'index'         => $this->reglasIndex(),
            'store'         => $this->reglasStore(),
            'lotesVencidos' => $this->reglasLotesVencidos(),
            default         => [],
        };
    }

    protected function reglasIndex(): array
    {
        return [
            'per_page'           => 'nullable|integer|min:1|max:100',
            'origen'             => 'nullable|in:DIRECTO,VENCIMIENTO,VENTA',
            'estado'             => 'nullable|in:REGISTRADO,RECHAZADO,DEVOLUCION,ANULADO',
            'fecha_inicio'       => 'nullable|date',
            'fecha_fin'          => 'nullable|date|after_or_equal:fecha_inicio',
            'buscar'             => 'nullable|string',
        ];
    }

    protected function reglasStore(): array
    {
        return [
            'producto_id' => 'required|exists:productos,id',
            'cantidad'    => 'required|integer|min:1',
            'descripcion' => 'required|string|max:255',
            'origen'      => 'required|in:DIRECTO,VENCIMIENTO',
            'lote_id'     => 'nullable|exists:lotes,id',
        ];
    }

    protected function reglasLotesVencidos(): array
    {
        return [
            'producto_id' => 'nullable|exists:productos,id',
        ];
    }

    public function messages(): array
    {
        return [
            'producto_id.required'  => 'El producto es obligatorio.',
            'producto_id.exists'    => 'El producto seleccionado no existe.',
            'cantidad.required'     => 'La cantidad es obligatoria.',
            'cantidad.min'          => 'La cantidad debe ser al menos 1.',
            'descripcion.required'  => 'La descripción del daño es obligatoria.',
            'origen.required'       => 'El origen del registro es obligatorio.',
            'origen.in'             => 'El origen seleccionado no es válido.',
            'lote_id.exists'        => 'El lote seleccionado no existe.',
        ];
    }
}