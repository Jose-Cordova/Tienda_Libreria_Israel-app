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

class VentaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try{

        //consulta base
        $query = Venta::with([
            'user',
            'metodoPago',
            'detalleVentas.producto',
            'detalleVentas.lote',
            'credito.clienteCredito'
        ]);

        //filtrar por estado de la venta
        if($request->estado){
            $query->where('estado', $request->estado);
        }

        //filtrar por usuario que realizó la venta
        if($request->user_id){
            $query->where('user_id', $request->user_id);
        }

        //filtrar por tipo de cliente
        if($request->tipoCliente){
            $query->where('tipo_cliente', $request->tipoCliente);
        }

        //filtrar por estado del producto
        if($request->estado_producto){

            $query->whereHas('detalleVentas.producto', function($q) use ($request){

                $q->where('estado', $request->estado_producto);

            });
        }

        //filtrar por metodo de pago
        if($request->metodo_pago_id){
            $query->where('metodo_pago_id', $request->metodo_pago_id);
        }

        //filtrar por fecha inicial
        if($request->fecha_inicio){
            $query->whereDate('fecha', '>=', $request->fecha_inicio);
        }

        //filtrar por fecha final
        if($request->fecha_fin){
            $query->whereDate('fecha', '<=', $request->fecha_fin);
        }

        //ordenamos por fecha descendente
        $ventas = $query->orderBy('fecha', 'desc')->get();

        //retornamos respuesta
        return response()->json($ventas, 200);

    }catch(Exception $e){

        //retornamos error
        return response()->json([
            'message' => 'Error al mostrar las ventas',
            'error' => $e->getMessage()
        ], 500);

    }

    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
    try{

        //validamos la data que viene por $request
        $data = $request->validate([

            //validaciones de venta
            'user_id' => 'required|exists:users,id',
            'metodo_pago_id' => 'required|exists:metodos_pagos,id',
            'tipo_cliente' => 'required|in:DETALLES,MAYORISTA',
            'estado' => 'required|in:PAGADA,CREDITO',

            //validaciones del detalle de venta
            'detalle' => 'required|array|min:1',
            'detalle.*.producto_id' => 'required|exists:productos,id',
            'detalle.*.cantidad' => 'required|integer|min:1',

            //validaciones adicionales si la venta es a CREDITO
            'cliente_credito_id' => 'nullable|exists:clientes_creditos,id',

            'nombre' => 'nullable|string|max:50',
            'dui' => 'nullable|string|max:10|unique:clientes_creditos,dui',
            'telefono' => 'nullable|string|max:20|unique:clientes_creditos,telefono',
        ]);

        //iniciamos transaccion
        DB::beginTransaction();

        //variable para almacenar total de venta
        $totalVenta = 0;

        //registramos venta
        $venta = Venta::create([
            'correlativo' => $this->generarCorrelativo(),
            'fecha' => now(),
            'total' => 0,
            'tipo_cliente' => $data['tipo_cliente'],
            'estado' => $data['estado'],
            'metodo_pago_id' => $data['metodo_pago_id'],
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

    //registramos credito
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

    }catch(Exception $e){

        //retornamos error
        return response()->json([
            'message' => 'Error al mostrar la venta',
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
        //
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
}
