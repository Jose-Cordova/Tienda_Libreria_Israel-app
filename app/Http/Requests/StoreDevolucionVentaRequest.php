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

        // Acumular las cantidades solicitadas en esta misma petición, por detalle_venta_id
        $sumasPorDetalleVenta = [];

        foreach ($detalles as $index => $detalle) {
            $detalleVentaId = $detalle['detalle_venta_id'];
            $cantidadSolicitada = $detalle['cantidad'];

            // Sumar para control interno
            if (!isset($sumasPorDetalleVenta[$detalleVentaId])) {
                $sumasPorDetalleVenta[$detalleVentaId] = 0;
            }
            $sumasPorDetalleVenta[$detalleVentaId] += $cantidadSolicitada;
        }

        // Ahora validar cada detalle contra el máximo disponible
        foreach ($detalles as $index => $detalle) {
            $detalleVentaId = $detalle['detalle_venta_id'];
            $detalleVenta = \App\Models\DetalleVenta::find($detalleVentaId);

            if (!$detalleVenta) {
                $validator->errors()->add("detalle.{$index}.detalle_venta_id", 'La línea de venta seleccionada no existe.');
                continue;
            }

            // Verificar pertenencia a la venta
            if ($detalleVenta->venta_id != $ventaId) {
                $validator->errors()->add("detalle.{$index}.detalle_venta_id", 'Esta línea de venta no pertenece a la venta especificada.');
                continue;
            }

            $cantidadOriginal = $detalleVenta->cantidad;

            // Devoluciones previas (sin contar esta solicitud, que aún no se guarda)
            $devueltoPrevio = \App\Models\DetalleDevolucionVenta::where('detalle_venta_id', $detalleVentaId)
                ->whereHas('devolucionVenta', function ($q) {
                    $q->where('estado', 'DEVUELTA'); // solo devoluciones activas
                })
                ->sum('cantidad');

            $maximoDisponible = $cantidadOriginal - $devueltoPrevio;

            // Validar cantidad individual (ya lo hace la regla, pero por claridad)
            if ($detalle['cantidad'] > $maximoDisponible) {
                $validator->errors()->add(
                    "detalle.{$index}.cantidad",
                    "La cantidad a devolver ({$detalle['cantidad']}) supera la máxima disponible ({$maximoDisponible}) para esta línea de venta."
                );
            }

            // Validar que la suma total solicitada en esta petición para este detalle_venta_id no exceda el máximo
            if ($sumasPorDetalleVenta[$detalleVentaId] > $maximoDisponible) {
                // Solo agregamos el error una vez por detalle_venta_id (lo ponemos en el primer elemento que encuentra)
                // Pero como estamos en el bucle, podemos agregarlo al índice actual, aunque se repetirá. Mejor usar una bandera.
                // Para simplificar, agregamos un error genérico en el primer índice de ese detalle_venta_id.
                // Podemos usar una variable auxiliar para controlar si ya se reportó.
                // Implementaré una forma limpia: después del bucle, verificar y agregar errores.
            }
        }

        // Validación final de sumas por detalle_venta_id
        foreach ($sumasPorDetalleVenta as $detalleVentaId => $sumaCantidades) {
            $detalleVenta = \App\Models\DetalleVenta::find($detalleVentaId);
            if (!$detalleVenta) continue;

            $cantidadOriginal = $detalleVenta->cantidad;
            $devueltoPrevio = \App\Models\DetalleDevolucionVenta::where('detalle_venta_id', $detalleVentaId)
                ->whereHas('devolucionVenta', function ($q) {
                    $q->where('estado', 'DEVUELTA');
                })
                ->sum('cantidad');

            $maximoDisponible = $cantidadOriginal - $devueltoPrevio;

            if ($sumaCantidades > $maximoDisponible) {
                // Buscar el primer índice de este detalle_venta_id para asociar el error
                foreach ($detalles as $index => $detalle) {
                    if ($detalle['detalle_venta_id'] == $detalleVentaId) {
                        $validator->errors()->add(
                            "detalle.{$index}.cantidad",
                            "La suma de cantidades para esta línea de venta ({$sumaCantidades}) supera la máxima disponible ({$maximoDisponible})."
                        );
                        break;
                    }
                }
            }
        }
    });
}
}
