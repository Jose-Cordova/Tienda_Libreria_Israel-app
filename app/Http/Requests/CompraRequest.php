<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Models\Producto;
use App\Models\Lote;

class CompraRequest extends FormRequest
{
    public function authorize(): bool
    {
        //Verificar si el usuario actual esta autorizado para hacer esta accion
        return auth()->check();
    }
    //Normalizamos los codigos de lote antes de validar (sin espacios y en mayusculas)
    protected function prepareForValidation(): void
    {
        $detalles = $this->input('detalles');
        if(!is_array($detalles)){
            return;
        }
        foreach($detalles as $i => $detalle){
            if(!empty($detalle['lotes']) && is_array($detalle['lotes'])){
                foreach($detalle['lotes'] as $j => $lote){
                    if(isset($lote['codigo_lote']) && is_string($lote['codigo_lote'])){
                        $detalles[$i]['lotes'][$j]['codigo_lote'] = mb_strtoupper(trim($lote['codigo_lote']), 'UTF-8');
                    }
                }
            }
        }
        $this->merge(['detalles' => $detalles]);
    }

    //Definimos las reglas de validacion
    public function rules(): array
    {
        return [
            //Validacion para factura
            //Maximo de caracteres del DTE: Nº de Control (31) y Código de Generación (36)
            'numero_factura' => ['bail', 'required', 'string', 'max:31', 'unique:compras,numero_factura'],
            'codigo_factura' => ['bail', 'required', 'string', 'max:36', 'unique:compras,codigo_factura'],
            'fecha_emision' => 'required|date',
            'proveedor_id' => 'required|exists:proveedores,id',
            //Validacion para los detalles de compras
            'detalles' => 'required|array|min:1',
            'detalles.*.producto_id' => 'nullable|exists:productos,id',
            'detalles.*.cantidad' => 'nullable|integer|min:1|max:99999',
            'detalles.*.factor_conversion' => 'nullable|integer|min:1|max:1000',
            'detalles.*.precio_unitario' => 'required|numeric|min:0.01|max:99999.99',
            'detalles.*.margen_detalle' => 'required|numeric|min:0.01|max:100',
            'detalles.*.margen_mayor' => 'required|numeric|min:0.01|max:100',
            //Validacion para producto nuevo
            'detalles.*.nombre' => 'nullable|string|max:100',
            'detalles.*.stock_minimo' => 'nullable|integer|min:1|max:999999',
            'detalles.*.perecedero' => 'nullable|in:NORMAL,PERECEDERO',
            'detalles.*.marca_id' => 'nullable|exists:marcas,id',
            'detalles.*.categoria_id' => 'nullable|exists:categorias,id',
            'detalles.*.seccion' => 'nullable|in:TIENDA,LIBRERIA,MEDICAMENTO',
            //Validacion para los lotes cuando el producto es perecedero
            'detalles.*.lotes' => 'nullable|array|min:1',
            'detalles.*.lotes.*.codigo_lote' => 'nullable|string|max:50',
            'detalles.*.lotes.*.fecha_vencimiento' => 'nullable|date|after:today',
            'detalles.*.lotes.*.cantidad' => 'nullable|integer|min:1|max:99999'
        ];
    }
    // Agregamos validaciones adicionales
    public function withValidator(Validator $validator): void
    {
        // Obtenemos los detalles de la compra y los almacenamos para detectar si hay duplicados
        $validator->after(function ($validator){
            $detalles = $this->input('detalles', []);
            $productosExistentes = [];
            // Arreglo para rastrear nombres normalizados dentro de la misma compra
            $nombresNuevosEnCompra = [];
            // Consultamos los productos existentes en la base de datos de una sola vez
            $productosEnBd = Producto::select('id', 'nombre')->get();
            // Codigos de lote usados en esta compra y el detalle donde aparecen (unicidad global)
            $lotesEnCompra = [];

            // Recorremos cada producto del detalle enviado en la compra
            foreach($detalles as $index => $detalle){
                $productoId = $detalle['producto_id'] ?? null;
                $num = $index + 1;
                // Determinamos si el producto es perecedero
                $esPerecedero = $this->esProductoPerecedero($detalle);

                // Validar producto nuevo
                if(is_null($productoId)){
                    // Validar campos para producto nuevo
                    $camposRequeridos = [
                        'nombre' => 'El nombre del producto',
                        'stock_minimo' => 'El stock mínimo',
                        'perecedero' => 'El tipo de perecibilidad',
                        'marca_id' => 'La marca',
                        'categoria_id' => 'La categoría',
                        'seccion' => 'La sección de medida'
                    ];
                    // Recorremos cada uno de los campos para el producto nuevo
                    foreach($camposRequeridos as $campo => $etiqueta){
                        // Comprobamos que el campo no esté vacío
                        if(empty($detalle[$campo])){
                            $validator->errors()->add(
                                "detalles.$index.$campo",
                                "$etiqueta es requerido/a para el producto nuevo en el detalle #$num."
                            );
                        }
                    }

                    // Validación con nombre normalizado para evitar duplicados como Coca-Cola vs CocaCola
                    if(!empty($detalle['nombre'])){
                        $nombreNormalizado = $this->normalizarNombre($detalle['nombre']);

                        // 1. Verificar si ya existe un producto similar en la base de datos
                        $productoCoincidente = $productosEnBd->first(function ($prod) use ($nombreNormalizado) {
                            return $this->normalizarNombre($prod->nombre) === $nombreNormalizado;
                        });

                        if($productoCoincidente){
                            $validator->errors()->add(
                                "detalles.$index.nombre",
                                "Ya existe un producto similar ('{$productoCoincidente->nombre}') en la base de datos."
                            );
                        }

                        // 2. Verificar si se intenta agregar el mismo producto nuevo dos veces en esta compra
                        if(in_array($nombreNormalizado, $nombresNuevosEnCompra)){
                            $validator->errors()->add(
                                "detalles.$index.nombre",
                                "El producto '{$detalle['nombre']}' está duplicado en los detalles de esta misma compra."
                            );
                        } else {
                            $nombresNuevosEnCompra[] = $nombreNormalizado;
                        }
                    }

                }else{
                    // Validamos si el producto ya está en el detalle
                    if(in_array($productoId, $productosExistentes)){
                        $validator->errors()->add(
                            "detalles.$index.producto_id",
                            "El producto (ID: $productoId) ya fue agregado en otro detalle."
                        );
                    }else{
                        // Si no existe lo guardamos en el array
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
                        //Vencimientos ya registrados por codigo para este producto (sin contar lotes anulados)
                        $vencimientosEnBd = [];
                        if(!is_null($productoId)){
                            Lote::where('producto_id', $productoId)
                                ->where(function($q){
                                    $q->whereNull('motivo_inactivo')->orWhere('motivo_inactivo', '!=', 'ANULACION');
                                })
                                ->get(['codigo_lote', 'fecha_vencimiento'])
                                ->each(function($l) use (&$vencimientosEnBd){
                                    $vencimientosEnBd[mb_strtoupper(trim($l->codigo_lote), 'UTF-8')] = $l->fecha_vencimiento->toDateString();
                                });
                        }
                        //Codigos de este detalle que ya pertenecen a otro producto (sin contar lotes anulados)
                        $codigosDetalle = array_values(array_filter(array_column($detalle['lotes'], 'codigo_lote')));
                        $lotesOtrosProductos = [];
                        if(!empty($codigosDetalle)){
                            $marcadores = implode(',', array_fill(0, count($codigosDetalle), '?'));
                            Lote::with('producto:id,nombre')
                                ->whereRaw("UPPER(TRIM(codigo_lote)) IN ($marcadores)", $codigosDetalle)
                                ->when(!is_null($productoId), fn($q) => $q->where('producto_id', '!=', $productoId))
                                ->where(function($q){
                                    $q->whereNull('motivo_inactivo')->orWhere('motivo_inactivo', '!=', 'ANULACION');
                                })
                                ->get()
                                ->each(function($l) use (&$lotesOtrosProductos){
                                    $lotesOtrosProductos[mb_strtoupper(trim($l->codigo_lote), 'UTF-8')] = $l->producto->nombre ?? 'otro producto';
                                });
                        }
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

                                //Un mismo lote del proveedor no puede tener dos vencimientos distintos
                                $vencimientoBd = $vencimientosEnBd[$lote['codigo_lote']] ?? null;
                                if($vencimientoBd && !empty($lote['fecha_vencimiento'])
                                    && substr($lote['fecha_vencimiento'], 0, 10) !== $vencimientoBd){
                                    $validator->errors()->add(
                                        "detalles.$index.lotes.$loteIndex.fecha_vencimiento",
                                        "El lote '{$lote['codigo_lote']}' ya está registrado con vencimiento " . date('d/m/Y', strtotime($vencimientoBd)) . " en el detalle #$num."
                                    );
                                }

                                //El codigo de lote es unico en todo el sistema: no puede pertenecer a otro producto
                                if(isset($lotesOtrosProductos[$lote['codigo_lote']])){
                                    $validator->errors()->add(
                                        "detalles.$index.lotes.$loteIndex.codigo_lote",
                                        "El lote '{$lote['codigo_lote']}' ya pertenece al producto '{$lotesOtrosProductos[$lote['codigo_lote']]}' (detalle #$num)."
                                    );
                                }
                                //Tampoco puede repetirse entre productos distintos de esta misma compra
                                $detalleConCodigo = $lotesEnCompra[$lote['codigo_lote']] ?? null;
                                if(!is_null($detalleConCodigo) && $detalleConCodigo !== $num){
                                    $validator->errors()->add(
                                        "detalles.$index.lotes.$loteIndex.codigo_lote",
                                        "El lote '{$lote['codigo_lote']}' ya se usa en el detalle #$detalleConCodigo de esta compra."
                                    );
                                }else{
                                    $lotesEnCompra[$lote['codigo_lote']] = $num;
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

    //Funcion auxiliar para normalizar nombres de productos
    private function normalizarNombre(?string $nombre): string
    {
        if(!$nombre){
            return '';
        }
        //Convertir a misnusculas
        $texto = mb_strtolower(trim($nombre), 'UTF-8');
        //Remplazar vocales con acento
        $texto = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ü', 'ñ'],
            ['a', 'e', 'i', 'o', 'u', 'u', 'n'],
            $texto
        );
        //Eliminar todo lo que no sea letra o numero
        return preg_replace('/[^a-z0-9]/', '', $texto);
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
            'numero_factura.max' => 'El N° de Control admite máximo 31 caracteres.',
            'codigo_factura.required' => 'El Código de Generación es obligatorio.',
            'codigo_factura.unique' => 'Ya existe una compra registrada con ese Código de Generación en el sistema.',
            'codigo_factura.max' => 'El Código de Generación admite máximo 36 caracteres.',
            'fecha_emision.required' => 'La fecha de emisión es obligatoria.',
            'fecha_emision.date' => 'La fecha de emisión no tiene un formato válido.',
            'proveedor_id.required' => 'Debe seleccionar un proveedor.',
            'proveedor_id.exists' => 'El proveedor seleccionado no es válido.',

            'detalles.required' => 'Debe incluir al menos un producto en la compra.',
            'detalles.min' => 'Debe incluir al menos un producto en la compra.',
            'detalles.*.producto_id.exists' => 'El producto seleccionado no existe.',
            'detalles.*.precio_unitario.required'=> 'El precio unitario es obligatorio.',
            'detalles.*.precio_unitario.min' => 'El precio unitario debe ser mayor a 0.',
            'detalles.*.precio_unitario.max' => 'El precio unitario no puede ser mayor a $99,999.99.',
            'detalles.*.margen_detalle.required' => 'El margen de venta al detalle es obligatorio.',
            'detalles.*.margen_detalle.min' => 'El margen al detalle debe ser mayor a 0.',
            'detalles.*.margen_detalle.max' => 'El margen al detalle no puede ser mayor a 100%.',
            'detalles.*.margen_mayor.required' => 'El margen de venta al mayor es obligatorio.',
            'detalles.*.margen_mayor.min' => 'El margen al mayor debe ser mayor a 0.',
            'detalles.*.margen_mayor.max' => 'El margen al mayor no puede ser mayor a 100%.',
            'detalles.*.cantidad.max' => 'La cantidad no puede ser mayor a 99,999.',
            'detalles.*.perecedero.in' => 'El tipo de producto debe ser NORMAL o PERECEDERO.',
            'detalles.*.stock_minimo.min' => 'El stock mínimo debe ser al menos 1.',
            'detalles.*.stock_minimo.max' => 'El stock mínimo no puede ser mayor a 999,999.',
            'detalles.*.lotes.*.fecha_vencimiento.after' => 'La fecha de vencimiento debe ser posterior a hoy.',
            'detalles.*.lotes.*.cantidad.min' => 'La cantidad del lote debe ser al menos 1.',
            'detalles.*.lotes.*.cantidad.max' => 'La cantidad del lote no puede ser mayor a 99,999.',
            'detalles.*.factor_conversion.integer' => 'El factor de conversión debe ser un número entero.',
            'detalles.*.factor_conversion.min' => 'El factor de conversión debe ser al menos 1.',
            'detalles.*.factor_conversion.max' => 'El factor de conversión no puede ser mayor a 1,000.',
            'detalles.*.nombre.max' => 'El nombre del producto no puede tener más de 100 caracteres.'
        ];
    }

    //Nombres legibles para los mensajes automáticos (en lugar de "detalles.0.nombre")
    public function attributes(): array
    {
        return [
            'numero_factura' => 'N° de Control',
            'codigo_factura' => 'Código de Generación',
            'fecha_emision' => 'fecha de emisión',
            'proveedor_id' => 'proveedor',
            'detalles.*.nombre' => 'nombre del producto',
            'detalles.*.precio_unitario' => 'costo unitario',
            'detalles.*.cantidad' => 'cantidad',
            'detalles.*.margen_detalle' => 'margen al detalle',
            'detalles.*.margen_mayor' => 'margen al mayor',
            'detalles.*.factor_conversion' => 'factor de conversión',
            'detalles.*.stock_minimo' => 'stock mínimo',
            'detalles.*.categoria_id' => 'categoría',
            'detalles.*.marca_id' => 'marca',
            'detalles.*.seccion' => 'sección',
            'detalles.*.perecedero' => 'tipo de producto',
            'detalles.*.lotes.*.codigo_lote' => 'código de lote',
            'detalles.*.lotes.*.fecha_vencimiento' => 'fecha de vencimiento',
            'detalles.*.lotes.*.cantidad' => 'cantidad del lote',
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
