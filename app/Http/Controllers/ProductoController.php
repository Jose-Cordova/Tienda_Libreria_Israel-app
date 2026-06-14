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

        // Filtro por estado
        $estado = $request->query('estado');
        if ($estado !== null && in_array($estado, ['ACTIVO', 'INACTIVO'])) {
            $query->where('estado', $estado);
        }

        // Búsqueda por nombre, categoría y marca
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('productos.nombre', 'ilike', '%' . $search . '%')
                ->orWhereHas('categoria', function ($q2) use ($search) {
                    $q2->where('nombre', 'ilike', '%' . $search . '%');
                    })
                    ->orWhereHas('marca', function ($q2) use ($search) {
                        $q2->where('nombre', 'ilike', '%' . $search . '%');
                    });
            });
        }

        if ($request->filled('categoria_id')) {
            $query->where('categoria_id', $request->categoria_id);
        }

        if ($request->filled('marca_id')) {
            $query->where('marca_id', $request->marca_id);
        }

        $productos=$query->orderBy('id','desc')->paginate(10);


        return response()->json($productos, 200);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Error al obtener los productos.',
            'error' => $e->getMessage()
        ], 500);
    }
}

    public function store(ProductoRequest $request)
    {
        try {
            DB::beginTransaction();

            $producto = Producto::create([
                'nombre'           => $request->nombre,
                'precio_detalle'   => $request->precio_detalle,
                'precio_mayor'     => $request->precio_mayor,
                'stock'            => $request->cantidad_inicial,
                'stock_minimo'     => $request->stock_minimo,
                'perecedero'       => $request->perecedero,
                'estado'           => 'ACTIVO',
                'unidad_medida_id' => $request->unidad_medida_id,
                'marca_id'         => $request->marca_id,
                'categoria_id'     => $request->categoria_id
            ]);

            if ($request->perecedero === 'PERECEDERO') {
                $lote = Lote::create([
                    'fecha_vencimiento' => $request->fecha_vencimiento,
                    'codigo_lote'       => $request->codigo_lote,
                    'fecha_ingreso'     => now(),
                    'cantidad_inicial'  => $request->cantidad_inicial,
                    'cantidad_actual'   => $request->cantidad_inicial,
                    'estado'            => 'ACTIVO',
                    'producto_id'       => $producto->id
                ]);
            }

            DB::commit();

            return response()->json([
                'message'  => 'Producto creado correctamente.',
                'producto' => $producto->load('lotes')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
    'message' => 'Error interno del servidor.',
    'error' => $e->getMessage()
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
            'unidad_medida_id' => $request->unidad_medida_id,
            'marca_id'         => $request->marca_id,
            'categoria_id'     => $request->categoria_id
        ]);

        return response()->json([
            'message'  => 'Producto actualizado correctamente.',
            'producto' => $producto
        ], 200);

    } catch (ModelNotFoundException $e) {
        return response()->json(['message' => 'Producto no encontrado.'], 404);
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
    public function cambiarEstado($id)
    {
        try {
            $producto = Producto::findOrFail($id);
            $nuevoEstado = $producto->estado === 'ACTIVO' ? 'INACTIVO' : 'ACTIVO';
            $producto->update(['estado' => $nuevoEstado]);

            return response()->json([
                'message' => 'Estado actualizado correctamente',
                'estado'  => $nuevoEstado
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al cambiar el estado',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}

