<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Models\Producto;

class CompraRequest extends FormRequest
{
    public function authorize(): bool
    {
        //Verificar si el usuario actual esta autorizado para hacer esta accion
        return auth()->check();
    }
    //Definimos las reglas de validacion
    public function rules(): array
    {
        return [
            //Validacion para factura
            'numero_factura' => 'required|string|max:50|unique:compras,numero_factura',
            'codigo_factura' => 'required|string|max:50|unique:compras,codigo_factura',
            'fecha_emision' => 'required|date',
            'proveedor_id' => 'required|exists:proveedores,id',
            //Validacion para los detalles de compras
            'detalles' => 'required|array|min:1',
            'detalles.*.producto_id' => 'nullable|exists:productos,id',
            'detalles.*.cantidad' => 'nullable|integer|min:1',
            'detalles.*.factor_conversion' => 'nullable|integer|min:1',
            'detalles.*.precio_unitario' => 'required|numeric|min:0.01',
            'detalles.*.margen_detalle' => 'required|numeric|min:0.01',
            'detalles.*.margen_mayor' => 'required|numeric|min:0.01',
            //Validacion para producto nuevo
            'detalles.*.nombre' => 'nullable|string|max:100',
            'detalles.*.stock_minimo' => 'nullable|integer|min:1',
            'detalles.*.perecedero' => 'nullable|in:NORMAL,PERECEDERO',
            'detalles.*.marca_id' => 'nullable|exists:marcas,id',
            'detalles.*.categoria_id' => 'nullable|exists:categorias,id',
            'detalles.*.seccion' => 'nullable|in:DESPENSA,LIBRERIA,MEDICAMENTO',
            //Validacion para los lotes cuando el producto es perecedero
            'detalles.*.lotes' => 'nullable|array|min:1',
            'detalles.*.lotes.*.codigo_lote' => 'nullable|string|max:50',
            'detalles.*.lotes.*.fecha_vencimiento' => 'nullable|date|after:today',
            'detalles.*.lotes.*.cantidad' => 'nullable|integer|min:1'
        ];
    }
    //Agregamos validaciones adicionales
    public function withValidator(Validator $validator): void
    {
        //Obtenemos los detalles de la compra y los almanenamos para detectetar si hay duplicados
        $validator->after(function ($validator){
            $detalles = $this->input('detalles', []);
            $productosExistentes = [];
            //Recorremos cada producto del detalle enviado en la compra y lo obtenemos
            foreach($detalles as $index => $detalle){
                $productoId = $detalle['producto_id'] ?? null;
                $num = $index + 1;
                //Determinamos si el producto es perecedero
                $esPerecedero = $this->esProductoPerecedero($detalle);

                //Validar producto nuevo
                if(is_null($productoId)){
                    //Validar campos para producto nuevo
                    $camposRequeridos = [
                        'nombre' => 'El nombre del producto',
                        'stock_minimo' => 'El stock mínimo',
                        'perecedero' => 'El tipo de perecibilidad',
                        'marca_id' => 'La marca',
                        'categoria_id' => 'La categoría',
                        'seccion' => 'La sección de medida'
                    ];
                    //Recorremos cada uno de los campos para el producto nuevo
                    foreach($camposRequeridos as $campo => $etiqueta){
                        //Comprobamos que el campo no este vacio
                        if(empty($detalle[$campo])){
                            $validator->errors()->add(
                                "detalles.$index.$campo",
                                "$etiqueta es requerido/a para el producto nuevo en el detalle #$num."
                            );
                        }
                    }
                    //Validamos que no se cree el mismo producto 2 veses
                    if(!empty($detalle['nombre'])){
                        $existeProducto = Producto::whereRaw('LOWER(nombre) = ?', [strtolower($detalle['nombre'])])->exists();
                        if($existeProducto){
                            $validator->errors()->add(
                                "detalles.$index.nombre",
                                "Ya existe un producto con el nombre '{$detalle['nombre']}' en la base de datos."
                            );
                        }
                    }

                }else{
                    //Validamos si el producto ya esta en el detalle
                    if(in_array($productoId, $productosExistentes)){
                        $validator->errors()->add(
                            "detalles.$index.producto_id",
                            "El producto (ID: $productoId) ya fue agregado en otro detalle."
                        );
                    }else{
                        //Si no existe lo guardamos en el array
                        $productosExistentes[] = $productoId;
                    }
                }

                //Validar segun tipo de producto
                if($esPerecedero){
                    //Si es perecedero debe tener al menos un lote
                    if(empty($detalle['lotes'])){
                        $validator->errors()->add(
                            "detalles.$index.lotes",
                            "El producto perecedero en el detalle #$num debe tener al menos un lote."
                        );
                    }else{
                        //Validamos cada lote del detalle
                        $lotesExistentes = [];
                        foreach($detalle['lotes'] as $loteIndex => $lote){
                            $numLote = $loteIndex + 1;
                            //Verificamos que no falte el codigo de lote
                            if(empty($lote['codigo_lote'])){
                                $validator->errors()->add(
                                    "detalles.$index.lotes.$loteIndex.codigo_lote",
                                    "El código de lote es requerido en el lote #$numLote del detalle #$num."
                                );
                            }else{
                                //Verificamos que no se repita el mismo codigo de lote
                                if(in_array($lote['codigo_lote'], $lotesExistentes)){
                                    $validator->errors()->add(
                                        "detalles.$index.lotes.$loteIndex.codigo_lote",
                                        "El código de lote '{$lote['codigo_lote']}' ya fue agregado en este detalle."
                                    );
                                }else{
                                    $lotesExistentes[] = $lote['codigo_lote'];
                                }
                            }

                            //Verificamos que no falte la fecha de vencimiento
                            if(empty($lote['fecha_vencimiento'])){
                                $validator->errors()->add(
                                    "detalles.$index.lotes.$loteIndex.fecha_vencimiento",
                                    "La fecha de vencimiento es requerida en el lote #$numLote del detalle #$num."
                                );
                            }
                            //Verificamos que no falte la cantidad de lote
                            if(empty($lote['cantidad'])){
                                $validator->errors()->add(
                                    "detalles.$index.lotes.$loteIndex.cantidad",
                                    "La cantidad es requerida en el lote #$numLote del detalle #$num."
                                );
                            }
                        }
                    }
                }else{
                    //Si es normal debe venir directamente en el detalle
                    if(empty($detalle['cantidad'])){
                        $validator->errors()->add(
                            "detalles.$index.cantidad",
                            "La cantidad es requerida para el producto NORMAL en el detalle #$num."
                        );
                    }
                }
            }
        });
    }

    //Funcion que recibe un array de los datos de un detalle
    protected function esProductoPerecedero(array $detalle): bool
    {
        //Obtenemos el producto del detalle
        $productoId = $detalle['producto_id'] ?? null;
        //Si es producto nuevo se le agrega que es perecedero y si no normal
        if(is_null($productoId)){
            return ($detalle['perecedero'] ?? '') === 'PERECEDERO';
        }
        //Si el producto existe se consulta y devuelve true si es perecedero
        return Producto::where('id', $productoId)->value('perecedero') === 'PERECEDERO';
    }

    //Definimos los mensajes por si no se cumplen las validaciones
    public function messages(): array
    {
        return [
            'numero_factura.required' => 'El N° de Control es obligatorio.',
            'numero_factura.unique' => 'Ya existe una compra registrada con ese N° de Control en el sistema.',
            'codigo_factura.required' => 'El Código de Generación es obligatorio.',
            'codigo_factura.unique' => 'Ya existe una compra registrada con ese Código de Generación en el sistema.',
            'fecha_emision.required' => 'La fecha de emisión es obligatoria.',
            'fecha_emision.date' => 'La fecha de emisión no tiene un formato válido.',
            'proveedor_id.required' => 'Debe seleccionar un proveedor.',
            'proveedor_id.exists' => 'El proveedor seleccionado no es válido.',

            'detalles.required' => 'Debe incluir al menos un producto en la compra.',
            'detalles.min' => 'Debe incluir al menos un producto en la compra.',
            'detalles.*.producto_id.exists' => 'El producto seleccionado no existe.',
            'detalles.*.precio_unitario.required'=> 'El precio unitario es obligatorio.',
            'detalles.*.precio_unitario.min' => 'El precio unitario debe ser mayor a 0.',
            'detalles.*.margen_detalle.required' => 'El margen de venta al detalle es obligatorio.',
            'detalles.*.margen_detalle.min' => 'El margen al detalle debe ser mayor a 0.',
            'detalles.*.margen_mayor.required' => 'El margen de venta al mayor es obligatorio.',
            'detalles.*.margen_mayor.min' => 'El margen al mayor debe ser mayor a 0.',
            'detalles.*.perecedero.in' => 'El tipo de producto debe ser NORMAL o PERECEDERO.',
            'detalles.*.stock_minimo.min' => 'El stock mínimo debe ser al menos 1.',
            'detalles.*.lotes.*.fecha_vencimiento.after' => 'La fecha de vencimiento debe ser posterior a hoy.',
            'detalles.*.lotes.*.cantidad.min' => 'La cantidad del lote debe ser al menos 1.',
            'detalles.*.factor_conversion.integer' => 'El factor de conversión debe ser un número entero.',
            'detalles.*.factor_conversion.min' => 'El factor de conversión debe ser al menos 1.'
        ];
    }

    //Si las validaciones fallan se ejecuta esta funcion
    protected function failedValidation(Validator $validator)
    {
        //Se interrumpe la ejecucion y se lanza una exepcion
        throw new HttpResponseException(
            response()->json([
                'message' => 'Error de validacion.',
                'errors' => $validator->errors()
            ], 422)
        );
    }
}
