<?php
namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;


class ProductoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }
    public function rules(): array
{
    $id = $this->route('producto');

    return [
        'nombre'           => 'required|string|min:3|max:100|unique:productos,nombre'.($id ? ','.$id : ''),
        'precio_detalle'   => 'required|numeric|min:0.01',
        'precio_mayor'     => 'required|numeric|min:0.01',
        'stock_minimo'     => 'required|integer|min:1',
        'perecedero'       => 'required|in:NORMAL,PERECEDERO',
        'unidad_medida_id' => 'required|exists:unidades_medidas,id',
        'marca_id'         => 'required|exists:marcas,id',
        'categoria_id'     => 'required|exists:categorias,id',
        'cantidad_inicial'  => 'required|integer|min:1',
        'codigo_lote'       => 'nullable|string|max:50',
        'fecha_vencimiento' => 'nullable|date|after:today',
        'codigo_lote' => 'required|string|max:50|unique:lotes,codigo_lote',
    ];
}

    public function messages(): array
    {
        return [
        'nombre.required'=> 'El nombre del producto es obligatorio.',
        'nombre.unique'=> 'Ya existe un producto con ese nombre.',
        'nombre.min'=> 'El nombre debe tener al menos 3 caracteres.',
        'precio_detalle.required'=> 'El precio detalle es obligatorio.',
        'precio_detalle.numeric'=> 'El precio detalle debe ser un número.',
        'precio_detalle.min'=> 'El precio detalle debe ser mayor a 0.',
        'precio_mayor.required'=> 'El precio mayor es obligatorio.',
        'precio_mayor.numeric'=> 'El precio mayor debe ser un número.',
        'precio_mayor.min'=> 'El precio mayor debe ser mayor a 0.',
        'stock_minimo.required'=> 'El stock mínimo es obligatorio.',
        'stock_minimo.integer'=> 'El stock mínimo debe ser un número entero.',
        'stock_minimo.min'=> 'El stock mínimo debe ser al menos 1.',
        'perecedero.required'=> 'El tipo de producto es obligatorio.',
        'perecedero.in'=> 'El tipo debe ser NORMAL o PERECEDERO.',
        'unidad_medida_id.required' => 'La unidad de medida es obligatoria.',
        'unidad_medida_id.exists'=> 'La unidad de medida no existe.',
        'marca_id.required'=> 'La marca es obligatoria.',
        'marca_id.exists'=> 'La marca no existe.',
        'categoria_id.required'=> 'La categoría es obligatoria.',
        'categoria_id.exists'=> 'La categoría no existe.',
        'cantidad_inicial.required'=> 'La cantidad inicial es obligatoria.',
        'cantidad_inicial.integer'=> 'La cantidad inicial debe ser un número entero.',
        'cantidad_inicial.min'=> 'La cantidad inicial debe ser al menos 1.',
        'codigo_lote.max'=> 'El código de lote no puede exceder 50 caracteres.',
        'fecha_vencimiento.date'=> 'La fecha de vencimiento no es válida.',
        'fecha_vencimiento.after'=> 'La fecha de vencimiento debe ser mayor a hoy.',
        'codigo_lote.required' => 'El código de lote es obligatorio.',
        'codigo_lote.unique'   => 'Ya existe un lote con ese código.',
        'codigo_lote.max'      => 'El código de lote no puede exceder 50 caracteres.',
        ];
    }
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Error de validación.',
            'errors' => $validator->errors()
        ], 422));
    }
}
