<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\ProductoRequest;
use App\Models\Producto;
use App\Models\Lote;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class ProductoController extends Controller
{
    public function index(Request $request)
{
    try {
        $query = Producto::with(['categoria', 'marca', 'unidadMedida']);

        // Solo productos activos por defecto (si no se pide otro estado)
        if ($request->has('estado')) {
            $query->where('estado', $request->estado);
        } else {
            $query->where('estado', 'ACTIVO');
        }

        // Búsqueda por nombre
        if ($request->filled('search')) {
            $query->where('nombre', 'ilike', '%' . $request->search . '%');
        }

        // Filtro por categoría
        if ($request->filled('categoria_id')) {
            $query->where('categoria_id', $request->categoria_id);
        }

        // Filtro por marca (opcional, si lo necesitás más adelante)
        if ($request->filled('marca_id')) {
            $query->where('marca_id', $request->marca_id);
        }

        // Ordenar del más reciente al más antiguo
        $productos = $query->orderBy('id', 'desc')->get();

        return response()->json($productos, 200);

    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Error al obtener los productos.',
            'error'   => $e->getMessage() // podés quitar en producción
        ], 500);
    }
}

    public function show(string $id)
    {
        try {
            $producto = Producto::findOrFail($id);
            return response()->json($producto, 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Producto no encontrado.'
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener el producto.'
            ], 500);
        }
    }

    public function update(ProductoRequest $request, string $id)
    {
        try {
            $producto = Producto::findOrFail($id);
            $producto->update([
                'nombre'           => $request->nombre,
                'precio_detalle'   => $request->precio_detalle,
                'precio_mayor'     => $request->precio_mayor,
                'stock_minimo'     => $request->stock_minimo,
                'perecedero'       => $request->perecedero,
                'unidad_medida_id' => $request->unidad_medida_id,
                'marca_id'         => $request->marca_id,
                'categoria_id'     => $request->categoria_id
            ]);

            return response()->json([
                'message'  => 'Producto actualizado correctamente.',
                'producto' => $producto
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Producto no encontrado.'
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error interno del servidor.'
            ], 500);
        }
    }

    public function cambiarEstado(string $id)
    {
        try {
        $producto = Producto::findOrFail($id);

        $producto->update([
            'estado' => $producto->estado === 'ACTIVO' ? 'INACTIVO' : 'ACTIVO'
        ]);

        return response()->json([
            'message' => 'Estado del producto actualizado correctamente.',
            'producto' => $producto
        ], 200);

    } catch (ModelNotFoundException $e) {
        return response()->json([
            'message' => 'Producto no encontrado.'
        ], 404);

    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Error interno del servidor.'
        ], 500);
    }
    }

    public function alertaStockMinimo()
    {
        try {
            $productos = Producto::where('estado', 'ACTIVO')
                ->whereColumn('stock', '<=', 'stock_minimo')
                ->get()
                ->map(function ($producto) {
                    return [
                        'id'      => $producto->id,
                        'nombre'  => $producto->nombre,
                        'stock'   => $producto->stock,
                        'mensaje' => "El producto {$producto->nombre} está a punto de agotarse, quedan {$producto->stock} unidades."
                    ];
                });

            return response()->json($productos, 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener los productos con stock mínimo.'
            ], 500);
        }
    }
}
