<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class ReporteController extends Controller
{
    /**
     * Devuelve datos JSON para la vista Vue
     */

    public function resumenJson(Request $request)
    {
        $request->validate([
            'fecha_inicio' => 'nullable|date',
            'fecha_fin'    => 'nullable|date|after_or_equal:fecha_inicio',
        ]);

        return response()->json([
            'ventas'  => $this->obtenerVentas($request)->values(),
            'compras' => $this->obtenerCompras($request)->values(),
        ]);
    }

    /**
     * Genera el PDF del reporte general con DomPDF
     */
    public function reporteGeneral(Request $request)
    {
        $request->validate([
            'fecha_inicio' => 'nullable|date',
            'fecha_fin'    => 'nullable|date|after_or_equal:fecha_inicio',
        ]);


        $fechaInicio = $request->fecha_inicio ?? 'Inicio';
        $fechaFin    = $request->fecha_fin    ?? 'Hoy';

        $ventas  = $this->obtenerVentas($request);
        $compras = $this->obtenerCompras($request);

        $ventasPagadas      = $ventas->where('estado', 'PAGADA');
        $totalCaja          = $ventasPagadas->sum('total');
        $totalDeudas        = $ventas->where('estado', 'CREDITO')->sum('total');
        // Se filtran las ventas pagadas por método de pago para calcular los totales
        $totalEfectivo      = $ventasPagadas->filter(fn($v) => strtoupper($v['metodo_pago']) === 'EFECTIVO')->sum('total');
        $totalTransferencia = $ventasPagadas->filter(fn($v) => strtoupper($v['metodo_pago']) === 'TRANSFERENCIA')->sum('total');
        $totalRegistrosV    = $ventas->count();

        $totalCompras    = $compras->sum('total');
        $totalRegistrosC = $compras->count();
        $ganancia        = $totalCaja - $totalCompras;

        return Pdf::loadView('reportes.General', compact(
            'ventas', 'totalRegistrosV', 'totalCaja', 'totalDeudas',
            'totalEfectivo', 'totalTransferencia',
            'compras', 'totalCompras', 'totalRegistrosC',
            'ganancia', 'fechaInicio', 'fechaFin'
        ))
        ->setPaper('a4', 'portrait')
        ->stream('reporte_general.pdf');
    }

    // Se crea un método privado para obtener las ventas filtradas según los parámetros de la solicitud
    private function obtenerVentas(Request $request)
    {
        // Preparamos la consulta
        $query = DB::table('ventas as v')
            ->join('metodos_pagos as mp', 'v.metodo_pago_id', '=', 'mp.id')
            ->leftJoin('creditos as c', 'v.id', '=', 'c.venta_id')
            ->leftJoin('clientes_creditos as cc', 'c.cliente_credito_id', '=', 'cc.id')

            ->select(
                'v.correlativo', 'v.fecha', 'v.tipo_cliente', 'v.estado', 'v.total',
                'mp.nombre as metodo_pago',
                'cc.nombre as cliente_credito_nombre',
                // Se agrega una subconsulta para contar la cantidad de artículos en cada venta
                DB::raw('(SELECT COALESCE(SUM(cantidad), 0) FROM detalle_ventas WHERE venta_id = v.id) as articulos')
            )
            ->whereIn('v.estado', ['PAGADA', 'CREDITO'])
            ->orderBy('v.fecha', 'desc');

        if ($request->fecha_inicio) $query->whereDate('v.fecha', '>=', $request->fecha_inicio);
        if ($request->fecha_fin)    $query->whereDate('v.fecha', '<=', $request->fecha_fin);

        return $query->get()->map(function ($v) {
            return [
                'correlativo'  => $v->correlativo,
                'cliente'      => $v->estado === 'CREDITO' ? ($v->cliente_credito_nombre ?? 'Sin nombre') : 'Consumidor final',
                'fecha'        => Carbon::parse($v->fecha)->format('d/m/Y'),
                'tipo_cliente' => $v->tipo_cliente,
                'metodo_pago'  => $v->metodo_pago,
                'articulos'    => $v->articulos,
                'total'        => $v->total,
                'estado'       => $v->estado,
            ];
        });
    }

    private function obtenerCompras(Request $request)
    {
        $query = DB::table('compras as c')
            ->join('proveedores as p', 'c.proveedor_id', '=', 'p.id')
            ->select(
                'p.nombre as proveedor', 'p.telefono',
                'c.numero_factura', 'c.fecha_registro', 'c.total',
                // Se agrega una subconsulta para contar la cantidad de productos en cada compra
                DB::raw('(SELECT COALESCE(SUM(cantidad), 0) FROM detalle_compras WHERE compra_id = c.id) as productos')
            )
            ->where('c.estado', 'REGISTRADA')
            ->orderBy('c.fecha_registro', 'desc');

        if ($request->fecha_inicio) $query->whereDate('c.fecha_registro', '>=', $request->fecha_inicio);
        if ($request->fecha_fin)    $query->whereDate('c.fecha_registro', '<=', $request->fecha_fin);

        return $query->get()->map(function ($c) {
            return [
                'proveedor'      => $c->proveedor,
                'telefono'       => $c->telefono,
                'numero_factura' => $c->numero_factura,
                'fecha'          => Carbon::parse($c->fecha_registro)->format('d/m/Y'),
                'productos'      => $c->productos,
                'total'          => $c->total,
            ];
        });
    }
}
