<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Venta;
use App\Models\Credito;
use App\Models\Producto;
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

        $query = Venta::with([
            'user',
            'metodoPago',
            'detalleVentas.producto',

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
            $query->where('tipoCliente', $request->tipoCliente);
        }

        //orden en que se mostrarán las ventas
        $ventas = $query->orderBy('fecha', 'desc')->get();

        return response()->json($ventas);

    }catch(Exception $e){

        return response()->json([
            'message' => 'Error al obtener el listado de ventas',
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
            'fecha' => 'required|date',
            'tipo_cliente' => 'required|in:DETALLES,MAYORISTA',
            'estado' => 'required|in:PAGADA,CREDITO',

            //validaciones del detalle de venta
            'detalle' => 'required|array|min:1',
            'detalle.*.producto_id' => 'required|exists:productos,id',
            'detalle.*.cantidad' => 'required|integer|min:1',

            //validaciones adicionales si la venta es credito
            'cliente_credito_id' => 'required_if:estado,CREDITO|exists:clientes_creditos,id'
        ]);

        //iniciamos transaccion
        DB::beginTransaction();

        //variable para almacenar total de venta
        $totalVenta = 0;

        //registramos venta
        $venta = Venta::create([
            'correlativo' => $this->generarCorrelativo(),
            'fecha' => $data['fecha'],
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

            //validamos stock del producto
            if($producto->stock < $detalle['cantidad']){

                DB::rollBack();

                return response()->json([
                    'message' => 'Stock insuficiente para el producto '.$producto->nombre
                ], 400);
            }

            //validamos tipo de cliente para cambiar precio
            $precio = $data['tipo_cliente'] == 'MAYORISTA'
                ? $producto->precio_mayor
                : $producto->precio_detalle;

            //calculamos subtotal del detalle
            $subtotal = $precio * $detalle['cantidad'];

            //registramos detalle de venta
            DetalleVenta::create([
                'cantidad' => $detalle['cantidad'],
                'precio_unitario' => $precio,
                'subtotal' => $subtotal,
                'producto_id' => $producto->id,
                'venta_id' => $venta->id
            ]);

            //descontamos stock del producto
            $producto->stock -= $detalle['cantidad'];
            $producto->save();

            //sumamos subtotal al total de venta
            $totalVenta += $subtotal;
        }

        //actualizamos total final de la venta
        $venta->update([
            'total' => $totalVenta
        ]);

        //registramos credito si el estado de venta es credito
        if($data['estado'] == 'CREDITO'){

            Credito::create([
                'monto_adeudado' => $totalVenta,
                'saldo' => $totalVenta,
                'cliente_credito_id' => $data['cliente_credito_id'],
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
        //
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
