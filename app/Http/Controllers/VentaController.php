<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Venta;
use App\Models\Credito;
use App\Models\Producto;
use App\Models\ClienteCredito;
use App\Models\DetalleVenta;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Exception;
use Illuminate\Validation\ValidationException;
use App\Http\Requests\StoreVentaRequest;

class VentaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
{
    try {
        // Validar per_page
        $request->validate([
            'per_page' => 'nullable|integer|min:1|max:100'
        ]);

        // Consulta base
        $query = Venta::with([
            'user',
            'metodoPago',
            'detalleVentas.producto',
            'detalleVentas.lote',
            'credito.clienteCredito'
        ]);

        // Aplicar filtros (los mismos que ya tenés)
        if ($request->estado) {
            $query->where('estado', $request->estado);
        }
        if ($request->user_id) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->tipoCliente) {
            $query->where('tipo_cliente', $request->tipoCliente);
        }
        if ($request->estado_producto) {
            $query->whereHas('detalleVentas.producto', function($q) use ($request) {
                $q->where('estado', $request->estado_producto);
            });
        }
        if ($request->metodo_pago_id) {
            $query->where('metodo_pago_id', $request->metodo_pago_id);
        }
        // Filtrar por correlativo
        if ($request->filled('correlativo')) {
        $query->where('correlativo', $request->correlativo);
        }
        if ($request->fecha_inicio) {
            $query->whereDate('fecha', '>=', $request->fecha_inicio);
        }
        if ($request->fecha_fin) {
            $query->whereDate('fecha', '<=', $request->fecha_fin);
        }

        // Calcular totales globales (sin paginación, mismos filtros)
        $totalesQuery = clone $query;
        $totales = $totalesQuery->selectRaw("
            COUNT(*) as total_ventas,
            COUNT(CASE WHEN estado = 'PAGADA' THEN 1 END) as cantidad_pagadas,
            COALESCE(SUM(CASE WHEN estado = 'PAGADA' THEN total END), 0) as total_pagadas,
            COUNT(CASE WHEN estado = 'CREDITO' THEN 1 END) as cantidad_credito,
            COALESCE(SUM(CASE WHEN estado = 'CREDITO' THEN total END), 0) as total_credito,
            COUNT(CASE WHEN estado = 'ANULADA' THEN 1 END) as cantidad_anuladas,
            COALESCE(SUM(CASE WHEN estado = 'ANULADA' THEN total END), 0) as total_anuladas
        ")->first();

        // Paginación
        $perPage = $request->get('per_page', 15);
        $ventas = $query->orderBy('fecha', 'desc')->paginate($perPage);

        // Agregar totales a la respuesta
        $response = $ventas->toArray();
        $response['totales'] = [
            'pagadas' => [
                'cantidad' => (int) $totales->cantidad_pagadas,
                'total' => (float) $totales->total_pagadas,
            ],
            'credito' => [
                'cantidad' => (int) $totales->cantidad_credito,
                'total' => (float) $totales->total_credito,
            ],
            'anuladas' => [
                'cantidad' => (int) $totales->cantidad_anuladas,
                'total' => (float) $totales->total_anuladas,
            ],
        ];

        return response()->json($response, 200);

    } catch (Exception $e) {
        return response()->json([
            'message' => 'Error al mostrar las ventas',
            'error' => $e->getMessage()
        ], 500);
    }
}

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreVentaRequest $request)
    {
        $data = $request->validated();
    try{
        //iniciamos transaccion
        DB::beginTransaction();

        //variable para almacenar total de venta
        $totalVenta = 0;

        //registramos venta
        $venta = Venta::create([
            'correlativo' => $this->generarCorrelativo(),
            'fecha' => now(),
            'total' => 0,
            'monto_recibido' => $data['monto_recibido'] ?? null,
            'tipo_cliente' => $data['tipo_cliente'],
            'estado' => $data['estado'],
            'metodo_pago_id'  => $data['metodo_pago_id'] ?? null,
            'user_id' => $data['user_id']
        ]);

        //recorremos detalle para registrar detalle_ventas
        foreach($data['detalle'] as $detalle){

            //buscamos el producto
            $producto = Producto::findOrFail($detalle['producto_id']);

            //validamos stock general del producto
            if($producto->stock < $detalle['cantidad']){

                DB::rollBack();

                return response()->json([
                    'message' => 'Stock insuficiente para el producto '. $producto->nombre . '  le queda la cantidad restante de  ' . $producto->stock
                ], 400);
            }

            //validamos tipo de cliente para cambiar precio
$precio = $data['tipo_cliente'] == 'MAYORISTA'
    ? $producto->precio_mayor
    : $producto->precio_detalle;

//si el producto no es perecedero no necesita lote
if($producto->perecedero == 'NORMAL'){

    //calculamos subtotal del detalle
    $subtotal = $precio * $detalle['cantidad'];

    //registramos detalle de venta
    DetalleVenta::create([
        'cantidad' => $detalle['cantidad'],
        'precio_unitario' => $precio,
        'subtotal' => $subtotal,
        'producto_id' => $producto->id,
        'lote_id' => null,
        'venta_id' => $venta->id
    ]);

    //descontamos stock general del producto
    $producto->update([
        'stock' => $producto->stock - $detalle['cantidad']
    ]);

    //sumamos subtotal al total venta
    $totalVenta += $subtotal;

}else{

    //cantidad restante a descontar
    $cantidadRestante = $detalle['cantidad'];

    //obtenemos lotes FIFO del producto
    $lotes = $producto->lotes()
        ->where('estado', 'ACTIVO')
        ->where('cantidad_actual', '>', 0)
        ->orderBy('fecha_ingreso', 'asc')
        ->get();

    //validamos existencia de lotes
    if($lotes->isEmpty()){

        DB::rollBack();

        return response()->json([
            'message' => 'No existen lotes disponibles para el producto '.$producto->nombre
        ], 400);
    }

    //recorremos lotes para aplicar FIFO
    foreach($lotes as $lote){

        //omitimos lotes vencidos
        if($lote->fecha_vencimiento < now()->toDateString()){
            continue;
        }

        //si ya no queda cantidad salimos
        if($cantidadRestante <= 0){
            break;
        }

        //cantidad que descontaremos del lote actual
        $descuento = min($cantidadRestante, $lote->cantidad_actual);

        //calculamos subtotal del lote
        $subtotal = $precio * $descuento;

        //registramos detalle de venta
        DetalleVenta::create([
            'cantidad' => $descuento,
            'precio_unitario' => $precio,
            'subtotal' => $subtotal,
            'producto_id' => $producto->id,
            'lote_id' => $lote->id,
            'venta_id' => $venta->id
        ]);

        //descontamos stock del lote
        $lote->cantidad_actual -= $descuento;

        //si el lote llega a 0 cambiamos estado
        if($lote->cantidad_actual <= 0){
            $lote->estado = 'INACTIVO';
            $lote->motivo_inactivo = 'AGOTADO';
        }

        $lote->save();

        //restamos cantidad restante
        $cantidadRestante -= $descuento;

        //sumamos subtotal al total venta
        $totalVenta += $subtotal;
    }

    //validamos si no se pudo cubrir toda la cantidad
    if($cantidadRestante > 0){

        DB::rollBack();

        return response()->json([
            'message' => 'Stock insuficiente en lotes para el producto '.$producto->nombre
        ], 400);
    }

    //descontamos stock general del producto
    $producto->update([
        'stock' => $producto->stock - $detalle['cantidad']
    ]);
        }
    }
        //actualizamos total final de la venta
        $venta->update([
            'total' => $totalVenta
        ]);

        //registramos credito si el estado de venta es credito
        //registramos credito si el estado de venta es credito
    if($data['estado'] == 'CREDITO'){

    //validamos que exista cliente o datos para registrarlo
    if(
        empty($data['cliente_credito_id']) &&
        (
            empty($data['nombre']) ||
            empty($data['dui']) ||
            empty($data['telefono'])
        )
    ){

        DB::rollBack();

        return response()->json([
            'message' => 'Debe seleccionar un cliente crédito o registrar uno nuevo'
        ], 400);
    }

    //si no existe cliente_credito lo registramos
    if(empty($data['cliente_credito_id'])){

        $clienteCredito = ClienteCredito::create([
            'nombre' => $data['nombre'],
            'dui' => $data['dui'],
            'telefono' => $data['telefono']
        ]);

    }else{

        $clienteCredito = ClienteCredito::findOrFail($data['cliente_credito_id']);
    }

    //Registramos Credito
    Credito::create([
        'monto_adeudado' => $totalVenta,
        'saldo' => 0,
        'fecha_cancelada' => null,
        'cliente_credito_id' => $clienteCredito->id,
        'venta_id' => $venta->id
    ]);
}

        //confirmamos transaccion
        DB::commit();

        //retornamos respuesta exitosa
        return response()->json([
            'message' => 'Venta registrada correctamente',
            'venta' => $venta->load([
                'user',
                'metodoPago',
                'detalleVentas.producto',
                'detalleVentas.lote',
                'credito'
            ])
        ], 201);

    }catch(Exception $e){

        //revertimos transaccion en caso de error
        DB::rollBack();

        //retornamos error
        return response()->json([
            'message' => 'Error al registrar la venta',
            'error' => $e->getMessage()
        ], 500);

    }
}

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try{

        //buscamos venta
        $venta = Venta::with([
            'user',
            'metodoPago',
            'detalleVentas.producto',
            'detalleVentas.lote',
            'credito.clienteCredito'
        ])->findOrFail($id);

        //retornamos venta
        return response()->json([
            'venta' => $venta
        ], 200);

    } catch (ValidationException $e) {
    return response()->json([
        'message' => 'Datos inválidos',
        'errors' => $e->errors()
    ], 422);

} catch (\Illuminate\Database\QueryException $e) {
    DB::rollBack();
    // Si es error de unicidad en el DUI
    if (str_contains($e->getMessage(), 'clientes_creditos_dui_unique')) {
        return response()->json([
            'message' => 'El DUI ya ha sido registrado.'
        ], 422);
    }
    return response()->json([
        'message' => 'Error en la base de datos',
        'error' => $e->getMessage()
    ], 500);

} catch (Exception $e) {
    DB::rollBack();
    return response()->json([
        'message' => 'Error al registrar la venta',
        'error' => $e->getMessage()
    ], 500);
}
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
        DB::beginTransaction();

        $venta = Venta::with(['detalleVentas.lote', 'credito'])->findOrFail($id);

        if ($venta->estado === 'ANULADA') {
            return response()->json([
                'message' => 'La venta ya está anulada.'
            ], 400);
        }

        if (!in_array($venta->estado, ['PAGADA', 'CREDITO'])) {
            return response()->json([
                'message' => 'Solo se pueden anular ventas con estado PAGADA o CREDITO.'
            ], 400);
        }

        if ($venta->credito && $venta->credito->saldo != 0) {
            return response()->json([
                'message' => 'No se puede anular la venta porque ya se han registrado abonos al crédito.'
            ], 400);
        }

        foreach ($venta->detalleVentas as $detalle) {
            $producto = $detalle->producto;
            $cantidad = $detalle->cantidad;

            $producto->increment('stock', $cantidad);

            if ($detalle->lote_id && $detalle->lote) {
                $lote = $detalle->lote;
                $lote->cantidad_actual += $cantidad;
                if ($lote->estado === 'INACTIVO' && $lote->cantidad_actual > 0) {
                    $lote->estado = 'ACTIVO';
                    $lote->motivo_inactivo = null; // Limpiar motivo de inactivación
                }
                $lote->save();
            }
        }

        if ($venta->credito) {
            $venta->credito->delete();
        }

        $venta->update(['estado' => 'ANULADA']);

        DB::commit();

        return response()->json([
            'message' => 'Venta anulada correctamente.',
            'venta' => $venta->fresh([
                'user',
                'metodoPago',
                'detalleVentas.producto',
                'detalleVentas.lote',
                'credito'
            ])
        ], 200);

    } catch (ModelNotFoundException $e) {
        DB::rollBack();
        return response()->json([
            'message' => 'Venta no encontrada.'
        ], 404);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'message' => 'Error al anular la venta.',
            'error' => $e->getMessage()
        ], 500);
    }
    }
    private function generarCorrelativo()
    {
    $year = now()->format('Y');
    $month = now()->format('m');

    $ultimo = Venta::whereYear('fecha', $year)
        ->whereMonth('fecha', $month)
        ->count();

    $numero = str_pad($ultimo + 1, 4, '0', STR_PAD_LEFT);

    return $year . $month . $numero;
    }
    public function ticket($id)
{
    // Configuración de la tienda
    $config = DB::table('configuracion')->first();

    // Venta (con método de pago y usuario)
    $venta = DB::table('ventas')
                ->join('metodos_pagos', 'ventas.metodo_pago_id', '=', 'metodos_pagos.id')
                ->join('users', 'ventas.user_id', '=', 'users.id')
                ->where('ventas.id', $id)
                ->select('ventas.*', 'metodos_pagos.nombre as metodo_pago', 'users.name as vendedor')
                ->first();

    if (!$venta) {
        abort(404);
    }

    // Detalles AGRUPADOS por producto (evita líneas duplicadas por lote)
    $detalles = DB::table('detalle_ventas')
                    ->join('productos', 'detalle_ventas.producto_id', '=', 'productos.id')
                    ->where('detalle_ventas.venta_id', $id)
                    ->select(
                        'productos.nombre as producto',
                        DB::raw('SUM(detalle_ventas.cantidad) as cantidad'),
                        DB::raw('ROUND(MAX(detalle_ventas.precio_unitario), 2) as precio_unitario'),
                        DB::raw('ROUND(SUM(detalle_ventas.subtotal), 2) as subtotal')
                    )
                    ->groupBy('productos.id', 'productos.nombre')
                    ->get();

    // Información del crédito (si existe)
    $credito = DB::table('creditos')
                    ->join('clientes_creditos', 'creditos.cliente_credito_id', '=', 'clientes_creditos.id')
                    ->where('creditos.venta_id', $id)
                    ->select('clientes_creditos.nombre as cliente', 'creditos.monto_adeudado')
                    ->first();

    // Generar PDF
    $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('tickets.venta', compact('config', 'venta', 'detalles', 'credito'));

    // Configurar tamaño del papel (80mm de ancho, alto automático)
    $pdf->setPaper([0, 0, 226.77, 400], 'portrait'); // 80mm ≈ 226.77px

    return $pdf->stream("ticket-{$venta->correlativo}.pdf");
}
}
