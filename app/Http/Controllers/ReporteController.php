<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class ReporteController extends Controller
{
    public function reporteGeneral(Request $request)
    {
        //valida fechas si son correctas
        $request->validate([
            'fecha_inicio' => 'nullable|date',
            'fecha_fin'    => 'nullable|date|after_or_equal:fecha_inicio',
        ]);

        // Si no se envia fecha, muestra todo el historial
        $fechaInicio = $request->fecha_inicio ?? 'Inicio';
        $fechaFin    = $request->fecha_fin    ?? 'Hoy';


        // Trae todas las ventas PAGADAS y a CREDITO del periodo.
        // Une con metodo de pago y cliente credito si aplica.
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
                // Cuenta los articulos vendidos en cada venta
                DB::raw('(SELECT COALESCE(SUM(cantidad), 0) FROM detalle_ventas WHERE venta_id = v.id) as articulos')
            )
            ->whereIn('v.estado', ['PAGADA', 'CREDITO'])
            ->orderBy('v.fecha', 'desc');

        // Filtra por rango de fechas si se envian
        if ($request->fecha_inicio) {
            $ventasQuery->whereDate('v.fecha', '>=', $request->fecha_inicio);
        }
        if ($request->fecha_fin) {
            $ventasQuery->whereDate('v.fecha', '<=', $request->fecha_fin);
        }

        $ventas = $ventasQuery->get()->map(function ($v) {
            return [
                'correlativo'  => $v->correlativo,
                // Si es credito muestra el nombre del cliente, si no es consumidor final
                'cliente'      => $v->estado === 'CREDITO'
                                    ? ($v->cliente_credito_nombre ?? 'Sin nombre')
                                    : 'Consumidor final',
                'fecha'        => Carbon::parse($v->fecha)->format('d/m/Y'),
                'tipo_cliente' => $v->tipo_cliente,
                'metodo_pago'  => $v->metodo_pago,
                'articulos'    => $v->articulos,
                'total'        => $v->total,
                'estado'       => $v->estado,
            ];
        });

        // Total de registros
        $totalRegistrosV = $ventas->count();

        // pagada
        $totalCaja = $ventas->where('estado', 'PAGADA')->sum('total');

        // deuda pendiente
        $totalDeudas = $ventas->where('estado', 'CREDITO')->sum('total');

        // Separa los totales por metodo de pago nada mas ventas compradas
        $ventasPagadas      = $ventas->where('estado', 'PAGADA');
        $totalEfectivo      = $ventasPagadas->filter(fn($v) => strtoupper($v['metodo_pago']) === 'EFECTIVO')->sum('total');
        $totalTransferencia = $ventasPagadas->filter(fn($v) => strtoupper($v['metodo_pago']) === 'TRANSFERENCIA')->sum('total');

        // COMPRAS
        // Trae todas las compras registradas del periodo con su proveedor.
        $comprasQuery = DB::table('compras as c')
            ->join('proveedores as p', 'c.proveedor_id', '=', 'p.id')
            ->select(
                'p.nombre as proveedor',
                'p.telefono',
                'c.numero_factura',
                'c.fecha_registro',
                'c.total',
                // Cuenta los productos comprados en cada compra
                DB::raw('(SELECT COALESCE(SUM(cantidad), 0) FROM detalle_compras WHERE compra_id = c.id) as productos')
            )
            ->where('c.estado', 'REGISTRADA')
            ->orderBy('c.fecha_registro', 'desc');

        // Filtra por rango de fechas si se envian
        if ($request->fecha_inicio) {
            $comprasQuery->whereDate('c.fecha_registro', '>=', $request->fecha_inicio);
        }
        if ($request->fecha_fin) {
            $comprasQuery->whereDate('c.fecha_registro', '<=', $request->fecha_fin);
        }

        // Formatea cada compra para la vista
        $compras = $comprasQuery->get()->map(function ($c) {
            return [
                'proveedor'      => $c->proveedor,
                'telefono'       => $c->telefono,
                'numero_factura' => $c->numero_factura,
                'fecha'          => Carbon::parse($c->fecha_registro)->format('d/m/Y'),
                'productos'      => $c->productos,
                'total'          => $c->total,
            ];
        });

        $totalCompras    = $compras->sum('total');
        $totalRegistrosC = $compras->count();
        $ganancia = $totalCaja - $totalCompras;

        // Genera y retorna el PDF en el navegador
        return Pdf::loadView('reportes.general', compact(
            'ventas', 'totalRegistrosV', 'totalCaja', 'totalDeudas',
            'totalEfectivo', 'totalTransferencia',
            'compras', 'totalCompras', 'totalRegistrosC',
            'ganancia', 'fechaInicio', 'fechaFin'
        ))->setPaper('a4', 'portrait')->stream('reporte_general.pdf');
    }
}
