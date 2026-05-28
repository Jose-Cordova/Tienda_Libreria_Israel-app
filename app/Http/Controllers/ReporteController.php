<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class ReporteController extends Controller
{

    private function obtenerDatos(Request $request): array
    {
        $ventasQuery = DB::table('ventas as v')
            ->join('metodos_pagos as mp', 'v.metodo_pago_id', '=', 'mp.id')
            ->leftJoin('creditos as c', 'v.id', '=', 'c.venta_id')
            ->leftJoin('clientes_creditos as cc', 'c.cliente_credito_id', '=', 'cc.id')
            ->select(
                'v.correlativo',
                'v.fecha',
                'v.tipo_cliente',
                'v.estado',
                'v.total',
                'mp.nombre as metodo_pago',
                'cc.nombre as cliente_credito_nombre',
                DB::raw('(SELECT COALESCE(SUM(cantidad), 0) FROM detalle_ventas WHERE venta_id = v.id) as articulos')
            )
            ->whereIn('v.estado', ['PAGADA', 'CREDITO'])
            ->orderBy('v.fecha', 'desc');

        if ($request->fecha_inicio) {
            $ventasQuery->whereDate('v.fecha', '>=', $request->fecha_inicio);
        }
        if ($request->fecha_fin) {
            $ventasQuery->whereDate('v.fecha', '<=', $request->fecha_fin);
        }

        $ventas = $ventasQuery->get()->map(fn($v) => [
            'correlativo'  => $v->correlativo,
            'cliente'      => $v->estado === 'CREDITO'
                                ? ($v->cliente_credito_nombre ?? 'Sin nombre')
                                : 'Consumidor final',
            'fecha'        => Carbon::parse($v->fecha)->format('d/m/Y'),
            'tipo_cliente' => $v->tipo_cliente,
            'metodo_pago'  => $v->metodo_pago,
            'articulos'    => $v->articulos,
            'total'        => (float) $v->total,
            'estado'       => $v->estado,
        ]);

        $comprasQuery = DB::table('compras as c')
            ->join('proveedores as p', 'c.proveedor_id', '=', 'p.id')
            ->select(
                'p.nombre as proveedor',
                'p.telefono',
                'c.numero_factura',
                'c.fecha_registro',
                'c.total',
                DB::raw('(SELECT COALESCE(SUM(cantidad), 0) FROM detalle_compras WHERE compra_id = c.id) as productos')
            )
            ->where('c.estado', 'REGISTRADA')
            ->orderBy('c.fecha_registro', 'desc');

        if ($request->fecha_inicio) {
            $comprasQuery->whereDate('c.fecha_registro', '>=', $request->fecha_inicio);
        }
        if ($request->fecha_fin) {
            $comprasQuery->whereDate('c.fecha_registro', '<=', $request->fecha_fin);
        }

        $compras = $comprasQuery->get()->map(fn($c) => [
            'proveedor'      => $c->proveedor,
            'telefono'       => $c->telefono,
            'numero_factura' => $c->numero_factura,
            'fecha'          => Carbon::parse($c->fecha_registro)->format('d/m/Y'),
            'productos'      => $c->productos,
            'total'          => (float) $c->total,
        ]);

        $ventasPagadas = $ventas->where('estado', 'PAGADA');
        $ventasCredito = $ventas->where('estado', 'CREDITO');
        $totalCaja     = $ventasPagadas->sum('total');
        $totalDeudas   = $ventasCredito->sum('total');
        $totalCompras  = $compras->sum('total');

        return compact(
            'ventas', 'compras',
            'totalCaja', 'totalDeudas', 'totalCompras',
            'ventasPagadas', 'ventasCredito'
        );
    }
    public function resumenJson(Request $request)
    {
        $request->validate([
            'fecha_inicio' => 'nullable|date',
            'fecha_fin'    => 'nullable|date|after_or_equal:fecha_inicio',
        ]);

        $datos = $this->obtenerDatos($request);

        return response()->json([
            'resumen' => [
                'ingresos_caja' => $datos['totalCaja'],
                'fiado_total'   => $datos['totalDeudas'],
                'total_compras' => $datos['totalCompras'],
                'balance_neto'  => $datos['totalCaja'] - $datos['totalCompras'],
            ],
            'ventas'  => $datos['ventas']->values(),
            'compras' => $datos['compras']->values(),
        ]);
    }

    public function reporteGeneral(Request $request)
    {
        $request->validate([
            'fecha_inicio' => 'nullable|date',
            'fecha_fin'    => 'nullable|date|after_or_equal:fecha_inicio',
        ]);

        $fechaInicio = $request->fecha_inicio ?? 'Inicio';
        $fechaFin    = $request->fecha_fin    ?? 'Hoy';
        $datos       = $this->obtenerDatos($request);

        $ventas                = $datos['ventas'];
        $compras               = $datos['compras'];
        $totalCaja             = $datos['totalCaja'];
        $totalDeudas           = $datos['totalDeudas'];
        $totalCompras          = $datos['totalCompras'];
        $ganancia              = $totalCaja - $totalCompras;
        $totalRegistrosV       = $ventas->count();
        $totalRegistrosPagadas = $datos['ventasPagadas']->count();
        $totalRegistrosCredito = $datos['ventasCredito']->count();
        $totalRegistrosC       = $compras->count();

        return Pdf::loadView('reportes.general', compact(
            'ventas', 'totalRegistrosV', 'totalCaja', 'totalDeudas',
            'totalRegistrosPagadas', 'totalRegistrosCredito',
            'compras', 'totalCompras', 'totalRegistrosC',
            'ganancia', 'fechaInicio', 'fechaFin'
        ))->setPaper('a4', 'portrait')->stream('reporte_general.pdf');
    }
}
