<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CambioProductoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->route()?->getActionMethod()) {
            'index'   => $this->reglasIndex(),
            'store'   => $this->reglasStore(),
            'aceptar' => $this->reglasAceptar(),
            default   => [],
        };
    }

    protected function reglasIndex(): array
    {
        return [
            'per_page'           => 'nullable|integer|min:1|max:100',
            'estado_reclamacion' => 'nullable|in:PENDIENTE,ACEPTADO,RECHAZADO,ANULADO',
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
            'lote_id'     => 'nullable|exists:lotes,id',
        ];
    }

    protected function reglasAceptar(): array
    {
        $registro = \App\Models\CambioProducto::find($this->route('id'));
        $esPerecedero = $registro?->producto?->perecedero === 'PERECEDERO';

        $rules = [
            'tipo'                  => 'required|in:mismo,diferente',
            'reemplazo'             => 'nullable|in:MISMO_PRODUCTO,DIFERENTE_PRODUCTO',
            'producto_reemplazo_id' => 'nullable|exists:productos,id',
            'lote_opcion'           => 'nullable|in:mismo,nuevo',
            'codigo_lote'           => 'nullable|string|max:50',
            'fecha_vencimiento'     => 'nullable|date|after:today',
        ];

        // Si el tipo es "diferente" y NO se crea producto, el producto de reemplazo es obligatorio
        if ($this->tipo === 'diferente' && !$this->crear_producto) {
            $rules['producto_reemplazo_id'] = 'required|exists:productos,id';
        }

        // Datos para crear un producto nuevo (si aplica)
        if ($this->crear_producto) {
            $rules['nombre']           = 'required|string|min:3|max:100';
            $rules['marca_id']         = 'required|exists:marcas,id';
            $rules['categoria_id']     = 'required|exists:categorias,id';
            $rules['seccion']          = 'required|in:TIENDA,LIBRERIA,MEDICAMENTO';
            $rules['perecedero']       = 'required|in:NORMAL,PERECEDERO';
            $rules['precio_detalle']   = 'required|numeric|min:0.01';
            $rules['precio_mayor']     = 'required|numeric|min:0.01';
            $rules['stock_minimo']     = 'required|integer|min:1';
            $rules['costo_promedio']   = 'nullable|numeric|min:0';
        }

        // El producto que se recibe puede ser perecedero (original o reemplazo)
        $productoRecibido = null;
        if ($this->tipo === 'diferente' && $this->producto_reemplazo_id) {
            $productoRecibido = \App\Models\Producto::find($this->producto_reemplazo_id);
        } elseif ($this->tipo === 'mismo') {
            $productoRecibido = $registro?->producto;
        }
        if ($productoRecibido?->perecedero === 'PERECEDERO' && $this->lote_opcion !== 'mismo') {
            $rules['codigo_lote']       = 'required|string|max:50';
            $rules['fecha_vencimiento'] = 'required|date|after:today';
        }

        // Un producto nuevo perecedero siempre requiere un lote nuevo
        if ($this->crear_producto && $this->perecedero === 'PERECEDERO') {
            $rules['codigo_lote']       = 'required|string|max:50';
            $rules['fecha_vencimiento'] = 'required|date|after:today';
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'producto_id.required'  => 'El producto es obligatorio.',
            'producto_id.exists'    => 'El producto seleccionado no existe.',
            'cantidad.required'     => 'La cantidad es obligatoria.',
            'cantidad.min'          => 'La cantidad debe ser al menos 1.',
            'descripcion.required'  => 'La descripción del cambio es obligatoria.',
            'lote_id.exists'        => 'El lote seleccionado no existe.',
            'tipo.required'         => 'Debe indicar el tipo de reemplazo.',
            'tipo.in'               => 'El tipo de reemplazo no es válido.',
            'producto_reemplazo_id.required' => 'Debe seleccionar el producto de reemplazo.',
            'producto_reemplazo_id.exists'   => 'El producto de reemplazo no existe.',
            'codigo_lote.required'  => 'El código del lote es obligatorio para productos perecederos.',
            'fecha_vencimiento.required' => 'La fecha de vencimiento es obligatoria para productos perecederos.',
            'fecha_vencimiento.after'    => 'La fecha de vencimiento debe ser posterior a hoy.',
            'nombre.required'       => 'El nombre del producto nuevo es obligatorio.',
            'marca_id.required'     => 'La marca es obligatoria.',
            'categoria_id.required' => 'La categoría es obligatoria.',
            'seccion.required'      => 'La sección es obligatoria.',
            'precio_detalle.required' => 'El precio detalle es obligatorio.',
            'precio_mayor.required' => 'El precio mayor es obligatorio.',
            'stock_minimo.required' => 'El stock mínimo es obligatorio.',
        ];
    }
}
