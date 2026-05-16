<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class CompraRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'numero_factura' => [
                'required',
                'string',
                'max:50',
                Rule::unique('compras', 'numero_factura')
                    ->where('proveedor_id', $this->input('proveedor_id'))],

            'codigo_factura' => 'required|string|max:50',
            'fecha_emision' => 'required|date',
            'proveedor_id' => 'required|exists:proveedores,id',

            'detalles' => 'required|array|min:1',
            'detalles.*.producto_id' => 'nullable|exists:productos,id',
            'detalles.*.cantidad' => 'required|integer|min:1',
            'detalles.*.precio_unitario' => 'required|numeric|min:0.01',
            'detalles.*.margen_detalle' => 'required|numeric|min:0.01',
            'detalles.*.margen_mayor' => 'required|numeric|min:0.01',

            'detalles.*.nombre' => 'nullable|string|max:100',
            'detalles.*.stock_minimo' => 'nullable|integer|min:1',
            'detalles.*.perecedero' => 'nullable|in:NORMAL,PERECEDERO',
            'detalles.*.marca_id' => 'nullable|exists:marcas,id',
            'detalles.*.categoria_id' => 'nullable|exists:categorias,id',
            'detalles.*.unidad_medida_id' => 'nullable|exists:unidades_medidas,id',
            'detalles.*.codigo_lote' => 'nullable|string|max:50',
            'detalles.*.fecha_vencimiento' => 'nullable|date|after:today'
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator){
            $detalles = $this->input('detalles', []);
            $productosExistentes = [];

            foreach($detalles as $index => $detalle){
                $productoId = $detalle['producto_id'] ?? null;
                $num = $index + 1;

                if(!is_null($productoId)){
                    // Validar duplicados de productos existentes
                    if(in_array($productoId, $productosExistentes)){
                        $validator->errors()->add(
                            "detalles.$index.producto_id",
                            "El producto (ID: $productoId) ya fue agregado en otro detalle."
                        );
                    }else{
                        $productosExistentes[] = $productoId;
                    }
                }else{
                    // Validar campos para producto nuevo
                    $camposRequeridos = [
                        'nombre' => 'El nombre del producto',
                        'stock_minimo' => 'El stock mínimo',
                        'perecedero' => 'El tipo de perecibilidad',
                        'marca_id' => 'La marca',
                        'categoria_id' => 'La categoría',
                        'unidad_medida_id' => 'La unidad de medida'
                    ];

                    foreach($camposRequeridos as $campo => $etiqueta){
                        if(empty($detalle[$campo])){
                            $validator->errors()->add(
                                "detalles.$index.$campo",
                                "$etiqueta es requerido/a para el producto nuevo en el detalle #$num."
                            );
                        }
                    }
                }

                // Validar lote si es perecedero
                if($this->esProductoPerecedero($detalle)){
                    if (empty($detalle['codigo_lote'])) {
                        $validator->errors()->add(
                            "detalles.$index.codigo_lote",
                            "El código de lote es requerido para el producto perecedero en el detalle #$num."
                        );
                    }
                    if(empty($detalle['fecha_vencimiento'])){
                        $validator->errors()->add(
                            "detalles.$index.fecha_vencimiento",
                            "La fecha de vencimiento es requerida para el producto perecedero en el detalle #$num."
                        );
                    }
                }
            }
        });
    }

    protected function esProductoPerecedero(array $detalle): bool
    {
        $productoId = $detalle['producto_id'] ?? null;

        if(is_null($productoId)){
            return ($detalle['perecedero'] ?? '') === 'PERECEDERO';
        }

        return Producto::where('id', $productoId)->value('perecedero') === 'PERECEDERO';
    }

    public function messages(): array
    {
        return [
            'numero_factura.required' => 'El número de factura es obligatorio.',
            'numero_factura.unique' => 'Ya existe una compra registrada con ese número de factura para este proveedor.',
            'codigo_factura.required' => 'El código de factura es obligatorio.',
            'fecha_emision.required' => 'La fecha de emisión es obligatoria.',
            'fecha_emision.date' => 'La fecha de emisión no tiene un formato válido.',
            'proveedor_id.required' => 'Debe seleccionar un proveedor.',
            'proveedor_id.exists' => 'El proveedor seleccionado no es válido.',

            'detalles.required' => 'Debe incluir al menos un producto en la compra.',
            'detalles.min' => 'Debe incluir al menos un producto en la compra.',
            'detalles.*.producto_id.exists' => 'El producto seleccionado no existe.',
            'detalles.*.cantidad.required' => 'La cantidad es obligatoria en cada detalle.',
            'detalles.*.cantidad.min' => 'La cantidad debe ser al menos 1.',
            'detalles.*.precio_unitario.required' => 'El precio unitario es obligatorio.',
            'detalles.*.precio_unitario.min' => 'El precio unitario debe ser mayor a 0.',
            'detalles.*.margen_detalle.required' => 'El margen de venta al detalle es obligatorio.',
            'detalles.*.margen_detalle.min' => 'El margen al detalle debe ser mayor a 0.',
            'detalles.*.margen_mayor.required' => 'El margen de venta al mayor es obligatorio.',
            'detalles.*.margen_mayor.min' => 'El margen al mayor debe ser mayor a 0.',
            'detalles.*.perecedero.in' => 'El tipo de producto debe ser NORMAL o PERECEDERO.',
            'detalles.*.fecha_vencimiento.after' => 'La fecha de vencimiento debe ser posterior a hoy.',
            'detalles.*.stock_minimo.min' => 'El stock mínimo debe ser al menos 1.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'message' => 'Error de validacion.',
                'errors' => $validator->errors()
            ], 422)
        );
    }
}
