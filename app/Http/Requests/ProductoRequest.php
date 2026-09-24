<?php
namespace App\Http\Requests;

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
            'precio_mayor'     => [
                'required', 'numeric', 'min:0.01',
                function ($attribute, $value, $fail) {
                    if ($this->precio_detalle && (float)$value >= (float)$this->precio_detalle) {
                        $fail('El precio mayorista debe ser menor al precio detalle.');
                    }
                }
            ],
            'stock_minimo'     => 'required|integer|min:1',
            'perecedero'       => $isUpdate ? 'sometimes|in:NORMAL,PERECEDERO' : 'required|in:NORMAL,PERECEDERO',
            'marca_id'         => ['required', 'exists:marcas,id'],
            'categoria_id'     => ['required', 'exists:categorias,id'],
            'seccion'          => $isUpdate ? 'sometimes|in:TIENDA,LIBRERIA,MEDICAMENTO' : 'required|in:TIENDA,LIBRERIA,MEDICAMENTO',
            'nuevo_stock'      => 'nullable|integer|min:0',
            'motivo_ajuste'    => 'required_with:nuevo_stock|nullable|string|min:4|max:255',
            'lote_id'          => 'nullable|exists:lotes,id',
            // Nuevo lote opcional en edición
            'nuevo_lote'                    => 'nullable|array',
            'nuevo_lote.codigo_lote'        => 'required_with:nuevo_lote|nullable|string|max:50|unique:lotes,codigo_lote',
            'nuevo_lote.fecha_vencimiento'  => 'required_with:nuevo_lote|nullable|date|after:today',
            'nuevo_lote.cantidad'           => 'required_with:nuevo_lote|nullable|integer|min:1',
        ];

        // Solo para creación (store)
        if (!$isUpdate) {
            $rules['cantidad_inicial']           = 'required_if:perecedero,NORMAL|nullable|integer|min:1';
            $rules['lotes']                      = 'required_if:perecedero,PERECEDERO|nullable|array|min:1';
            $rules['lotes.*.codigo_lote']        = 'required|string|max:50|distinct|unique:lotes,codigo_lote';
            $rules['lotes.*.fecha_vencimiento']  = 'required|date|after:today';
            $rules['lotes.*.cantidad']           = 'required|integer|min:1';
        }

        // Validación de pertenencia de categoría y marca a la sección
        $rules['categoria_id'][] = function ($attribute, $value, $fail) {
            $seccion = $this->input('seccion');
            if ($seccion && $value) {
                $categoria = Categoria::find($value);
                if ($categoria && $categoria->seccion !== $seccion) {
                    $fail("La categoría seleccionada no pertenece a la sección '{$seccion}'.");
                }
            }
        };

        $rules['marca_id'][] = function ($attribute, $value, $fail) {
            $seccion = $this->input('seccion');
            if ($seccion && $value) {
                $marca = Marca::find($value);
                if ($marca && $marca->seccion !== $seccion) {
                    $fail("La marca seleccionada no pertenece a la sección '{$seccion}'.");
                }
            }
        };

        return $rules;
    }

    public function messages(): array
    {
        return [
            'nombre.required'                   => 'El nombre del producto es obligatorio.',
            'nombre.unique'                     => 'Ya existe un producto con ese nombre.',
            'nombre.min'                        => 'El nombre debe tener al menos 3 caracteres.',
            'precio_detalle.required'           => 'El precio detalle es obligatorio.',
            'precio_detalle.numeric'            => 'El precio detalle debe ser un número.',
            'precio_detalle.min'                => 'El precio detalle debe ser mayor a 0.',
            'precio_mayor.required'             => 'El precio mayor es obligatorio.',
            'precio_mayor.numeric'              => 'El precio mayor debe ser un número.',
            'precio_mayor.min'                  => 'El precio mayor debe ser mayor a 0.',
            'stock_minimo.required'             => 'El stock mínimo es obligatorio.',
            'stock_minimo.integer'              => 'El stock mínimo debe ser un número entero.',
            'stock_minimo.min'                  => 'El stock mínimo debe ser al menos 1.',
            'perecedero.required'               => 'El tipo de producto es obligatorio.',
            'perecedero.in'                     => 'El tipo debe ser NORMAL o PERECEDERO.',
            'marca_id.required'                 => 'La marca es obligatoria.',
            'marca_id.exists'                   => 'La marca no existe.',
            'categoria_id.required'             => 'La categoría es obligatoria.',
            'categoria_id.exists'               => 'La categoría no existe.',
            'cantidad_inicial.required_if'      => 'La cantidad inicial es obligatoria para productos normales.',
            'cantidad_inicial.integer'          => 'La cantidad inicial debe ser un número entero.',
            'cantidad_inicial.min'              => 'La cantidad inicial debe ser al menos 1.',
            'lotes.required_if'                 => 'Debe ingresar al menos un lote para productos perecederos.',
            'lotes.min'                         => 'Debe ingresar al menos un lote para productos perecederos.',
            'lotes.*.codigo_lote.required'      => 'El código de lote es obligatorio.',
            'lotes.*.codigo_lote.distinct'      => 'No puede haber códigos de lote duplicados en el formulario.',
            'lotes.*.codigo_lote.unique'        => 'Ya existe un lote registrado con ese código.',
            'lotes.*.codigo_lote.max'           => 'El código de lote no puede exceder 50 caracteres.',
            'lotes.*.fecha_vencimiento.required' => 'La fecha de vencimiento es obligatoria.',
            'lotes.*.fecha_vencimiento.date'     => 'La fecha de vencimiento no es válida.',
            'lotes.*.fecha_vencimiento.after'    => 'La fecha de vencimiento debe ser posterior a hoy.',
            'lotes.*.cantidad.required'         => 'La cantidad del lote es obligatoria.',
            'lotes.*.cantidad.integer'          => 'La cantidad del lote debe ser un número entero.',
            'lotes.*.cantidad.min'              => 'La cantidad de cada lote debe ser al menos 1.',
            'motivo_ajuste.required_with'       => 'El motivo del ajuste de stock es obligatorio al modificar el stock.',
            'motivo_ajuste.min'                 => 'El motivo del ajuste debe tener al menos 4 caracteres.',
            'nuevo_lote.codigo_lote.required_with' => 'El código del nuevo lote es obligatorio.',
            'nuevo_lote.codigo_lote.unique'        => 'Ya existe un lote registrado con ese código.',
            'nuevo_lote.fecha_vencimiento.required_with' => 'La fecha de vencimiento del nuevo lote es obligatoria.',
            'nuevo_lote.fecha_vencimiento.after'   => 'La fecha de vencimiento debe ser posterior a hoy.',
            'nuevo_lote.cantidad.required_with'    => 'La cantidad del nuevo lote es obligatoria.',
            'nuevo_lote.cantidad.min'              => 'La cantidad del nuevo lote debe ser al menos 1.',
            'seccion.required'                  => 'La sección del producto es obligatoria.',
            'seccion.in'                        => 'La sección debe ser TIENDA, LIBRERIA o MEDICAMENTO.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Error de validación.',
            'errors'  => $validator->errors()
        ], 422));
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function($validator){
            $nombre = $this->input('nombre');
            $id = $this->route('producto');

            if ($nombre) {
                $nombreNormalizado = $this->normalizarNombre($nombre);

                $query = Producto::select('id', 'nombre');
                if ($id) {
                    $query->where('id', '!=', $id);
                }

                $productoExistente = $query->get()->first(function ($prod) use ($nombreNormalizado){
                    return $this->normalizarNombre($prod->nombre) === $nombreNormalizado;
                });

                if ($productoExistente) {
                    $validator->errors()->add(
                        'nombre',
                        "Ya existe un producto similar ('{$productoExistente->nombre}') en el catálogo."
                    );
                }
            }
        });
    }

    private function normalizarNombre(?string $nombre): string
    {
        if (!$nombre) {
            return '';
        }
        $texto = mb_strtolower(trim($nombre), 'UTF-8');
        $texto = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ü', 'ñ'],
            ['a', 'e', 'i', 'o', 'u', 'u', 'n'],
            $texto
        );
        return preg_replace('/[^a-z0-9]/', '', $texto);
    }
}
