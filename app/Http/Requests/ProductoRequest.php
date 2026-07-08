<?php
namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Models\Producto;
use App\Models\Categoria;
use App\Models\Marca;

class ProductoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }
    public function rules(): array
{
    $id = $this->route('producto');
    $isUpdate = $id !== null;
    $rules = [
        'nombre'           => 'required|string|min:3|max:100|unique:productos,nombre'.($id ? ','.$id : ''),
        'precio_detalle'   => 'required|numeric|min:0.01',
        'precio_mayor'     => 'required|numeric|min:0.01',
        'stock_minimo'     => 'required|integer|min:1',
        'perecedero'       => 'required|in:NORMAL,PERECEDERO',
        'marca_id'         => ['required', 'exists:marcas,id'],
    'categoria_id'     => ['required', 'exists:categorias,id'],
        // para sesion: en creación es obligatorio, en update es opcional
        'sesion'           => $isUpdate ? 'sometimes|in:DESPENSA,LIBRERIA,MEDICAMENTO' : 'required|in:DESPENSA,LIBRERIA,MEDICAMENTO',

    ];

    // Solo para creación (store)
    if (!$isUpdate) {
        $rules['cantidad_inicial'] = 'required|integer|min:1';
        $rules['codigo_lote'] = 'required_if:perecedero,PERECEDERO|string|max:50|unique:lotes,codigo_lote';
        $rules['fecha_vencimiento'] = 'required_if:perecedero,PERECEDERO|date|after:today';
    }
    //Validacion para Categioria y Marca debe de coicidir con la sesión
     $rules['categoria_id'][] = function ($attribute, $value, $fail) use ($id) {
            $this->validarPertenencia($attribute, $value, $fail, $id);
        };
        $rules['marca_id'][] = function ($attribute, $value, $fail) use ($id) {
            $this->validarPertenencia($attribute, $value, $fail, $id);
        };
    return $rules;
}
    //VALIDAR QUE LA CATEGORIA Y MARCA PERTENEZCAN A LA SESION DEL PRODUCTO
    private function validarPertenencia($attribute, $value, $fail, $id)
    {
        // Determinar qué sesión debe tener el producto
        $sesion = $this->input('sesion');
        if ($id && !$this->has('sesion')) {
            // En actualización sin enviar sesion, usar la sesión actual del producto
            $producto = Producto::find($id);
            if ($producto) {
                $sesion = $producto->sesion;
            }
        }
        if (!$sesion) {
            return;
        }

        // Buscar el modelo (categoría o marca) con el ID enviado
        $modelo = $attribute === 'categoria_id' ? Categoria::find($value) : Marca::find($value);
        if (!$modelo) return;

        // Comparar sesiones
        if ($modelo->sesion !== $sesion) {
            $nombreModelo = $attribute === 'categoria_id' ? 'categoría' : 'marca';
            $fail("La {$nombreModelo} seleccionada no pertenece a la sección {$sesion}.");
        }
    }

    public function messages(): array
    {
        return [

'nombre.required' => 'El nombre del producto es obligatorio.',
            'nombre.unique'   => 'Ya existe un producto con ese nombre.',
            'nombre.min'      => 'El nombre debe tener al menos 3 caracteres.',
            'precio_detalle.required' => 'El precio detalle es obligatorio.',
            'precio_detalle.numeric'  => 'El precio detalle debe ser un número.',
            'precio_detalle.min'      => 'El precio detalle debe ser mayor a 0.',
            'precio_mayor.required'   => 'El precio mayor es obligatorio.',
            'precio_mayor.numeric'    => 'El precio mayor debe ser un número.',
            'precio_mayor.min'        => 'El precio mayor debe ser mayor a 0.',
            'stock_minimo.required'   => 'El stock mínimo es obligatorio.',
            'stock_minimo.integer'    => 'El stock mínimo debe ser un número entero.',
            'stock_minimo.min'        => 'El stock mínimo debe ser al menos 1.',
            'perecedero.required'     => 'El tipo de producto es obligatorio.',
            'perecedero.in'           => 'El tipo debe ser NORMAL o PERECEDERO.',
            'marca_id.required'       => 'La marca es obligatoria.',
            'marca_id.exists'         => 'La marca no existe.',
            'categoria_id.required'   => 'La categoría es obligatoria.',
            'categoria_id.exists'     => 'La categoría no existe.',
            'cantidad_inicial.required' => 'La cantidad inicial es obligatoria.',
            'cantidad_inicial.integer'  => 'La cantidad inicial debe ser un número entero.',
            'cantidad_inicial.min'      => 'La cantidad inicial debe ser al menos 1.',
            'codigo_lote.required'    => 'El código de lote es obligatorio para productos perecederos.',
            'codigo_lote.unique'      => 'Ya existe un lote con ese código.',
            'codigo_lote.max'         => 'El código de lote no puede exceder 50 caracteres.',
            'fecha_vencimiento.date'  => 'La fecha de vencimiento no es válida.',
            'fecha_vencimiento.after' => 'La fecha de vencimiento debe ser mayor a hoy.',
            'sesion.required' => 'La sección del producto es obligatoria.',
            'sesion.in'       => 'La sección debe ser DESPENSA, LIBRERIA o MEDICAMENTO.',
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
