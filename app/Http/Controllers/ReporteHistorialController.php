<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class ReporteHistorialController extends Controller
{
    public function reporteHistorial(Request $request)
    {
        $request->validate([
            'fecha_inicio'   => 'nullable|date',
            'fecha_fin'      => 'nullable|date|after_or_equal:fecha_inicio',
            'estado'         => 'nullable|in:PAGADA,CREDITO,ANULADA',
            'metodo_pago_id' => 'nullable|integer|exists:metodos_pagos,id',
            'tipo_cliente'   => 'nullable|in:DETALLES,MAYORISTA',
        ]);

        $fechaInicio = $request->fecha_inicio
            ? Carbon::parse($request->fecha_inicio)->format('d/m/Y')
            : 'Inicio';
        $fechaFin = $request->fecha_fin
            ? Carbon::parse($request->fecha_fin)->format('d/m/Y')
            : 'Hoy';

        $ventas = $this->obtenerVentas($request);

        $totalRegistros     = $ventas->count();
        $ventasPagadas      = $ventas->where('estado', 'PAGADA');
        $totalEfectivo      = $ventasPagadas->filter(fn($v) => strtoupper($v['metodo_pago']) === 'EFECTIVO')->sum('total');
        $totalTransferencia = $ventasPagadas->filter(fn($v) => strtoupper($v['metodo_pago']) === 'TRANSFERENCIA')->sum('total');
        $totalDeudas        = $ventas->where('estado', 'CREDITO')->sum('total');
        $totalAnuladas      = $ventas->where('estado', 'ANULADA')->sum('total');
        $totalCobrado       = $ventasPagadas->sum('total');
        $totalesPorMetodo   = $ventasPagadas->groupBy('metodo_pago')->map(fn($g) => $g->sum('total'));
        $generadoEn         = Carbon::now()->format('d/m/Y H:i');

        return Pdf::loadView('reportes.Historial', compact(
            'ventas', 'totalRegistros', 'totalEfectivo', 'totalTransferencia',
            'totalDeudas', 'totalAnuladas', 'totalCobrado', 'totalesPorMetodo',
            'fechaInicio', 'fechaFin', 'generadoEn'
        ))
        ->setPaper('a4', 'portrait')
        ->stream('reporte_historial_ventas.pdf');
    }

    public function historialDatos(Request $request)
    {
        $request->validate([
            'fecha_inicio' => 'nullable|date',
            'fecha_fin'    => 'nullable|date|after_or_equal:fecha_inicio',
            'estado'       => 'nullable|in:PAGADA,CREDITO,ANULADA',
            'tipo_cliente' => 'nullable|in:DETALLES,MAYORISTA',
        ]);

        $ventas = $this->obtenerVentas($request);

        return response()->json(['ventas' => $ventas->values()]);
    }

    private function obtenerVentas(Request $request)
    {
        $query = DB::table('ventas as v')
            ->leftJoin('metodos_pagos as mp', 'v.metodo_pago_id', '=', 'mp.id')
            ->leftJoin('creditos as c', 'v.id', '=', 'c.venta_id')
            ->leftJoin('clientes_creditos as cc', 'c.cliente_credito_id', '=', 'cc.id')
            ->select(
                'v.correlativo', 'v.fecha', 'v.tipo_cliente', 'v.estado', 'v.total',
                'mp.nombre as metodo_pago',
                'cc.nombre as cliente_credito_nombre',
                DB::raw('(SELECT COALESCE(SUM(cantidad), 0) FROM detalle_ventas WHERE venta_id = v.id) as articulos')
            )
            ->orderBy('v.fecha', 'desc');

        if ($request->estado)         $query->where('v.estado', $request->estado);
        if ($request->metodo_pago_id) $query->where('v.metodo_pago_id', $request->metodo_pago_id);
        if ($request->tipo_cliente)   $query->where('v.tipo_cliente', $request->tipo_cliente);
        if ($request->fecha_inicio)   $query->whereDate('v.fecha', '>=', $request->fecha_inicio);
        if ($request->fecha_fin)      $query->whereDate('v.fecha', '<=', $request->fecha_fin);

        return $query->get()->map(function ($v) {
            return [
                'correlativo'  => $v->correlativo,
                'fecha'        => Carbon::parse($v->fecha)->format('d/m/Y'),
                'hora'         => Carbon::parse($v->fecha)->format('H:i'),
                'cliente'      => $v->estado === 'CREDITO'
                                    ? ($v->cliente_credito_nombre ?? 'Sin nombre')
                                    : 'Consumidor final',
                'tipo_cliente' => ucfirst(strtolower($v->tipo_cliente)),
                'metodo_pago'  => $v->metodo_pago ?? '—',
                'estado'       => $v->estado,
                'articulos'    => $v->articulos,
                'total'        => $v->total,
            ];
        });
    }
}
