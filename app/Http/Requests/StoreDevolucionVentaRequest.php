<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDevolucionVentaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'venta_id' => 'required|exists:ventas,id',
            'motivo'   => 'required|string|max:255',

            'detalle'                    => 'required|array|min:1',
            'detalle.*.detalle_venta_id' => 'required|exists:detalle_ventas,id',
            'detalle.*.cantidad'         => 'required|integer|min:1',
            'detalle.*.condicion'        => 'required|in:PERFECTO,DANIADO',
            'detalle.*.descripcion'      => 'required_if:detalle.*.condicion,DANIADO|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'detalle.*.descripcion.required_if' => 'La descripción del daño es obligatoria cuando la condición es DANIADO.',
        ];
    }

    public function withValidator($validator)
    {
        // Si no se envió 'detalle', no hacemos nada (la validación required ya falló)
        if (!$this->has('detalle')) {
            return;
        }

        $validator->after(function ($validator) {
            $detalles = $this->input('detalle');
            $ventaId = $this->input('venta_id');

            foreach ($detalles as $index => $detalle) {
                $detalleVenta = \App\Models\DetalleVenta::find($detalle['detalle_venta_id']);

                if (!$detalleVenta) {
                    $validator->errors()->add("detalle.{$index}.detalle_venta_id", 'La línea de venta seleccionada no existe.');
                    continue;
                }

                if ($detalleVenta->venta_id != $ventaId) {
                    $validator->errors()->add("detalle.{$index}.detalle_venta_id", 'Esta línea de venta no pertenece a la venta especificada.');
                    continue;
                }

                $cantidadOriginal = $detalleVenta->cantidad;
                $devueltoPrevio = \App\Models\DetalleDevolucionVenta::where('detalle_venta_id', $detalle['detalle_venta_id'])
                    ->sum('cantidad');
                $maximo = $cantidadOriginal - $devueltoPrevio;

                if ($detalle['cantidad'] > $maximo) {
                    $validator->errors()->add(
                        "detalle.{$index}.cantidad",
                        "La cantidad a devolver ({$detalle['cantidad']}) supera la disponible. Máximo: {$maximo}."
                    );
                }
            }
        });
    }
}
