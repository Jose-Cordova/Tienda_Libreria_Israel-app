<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Configuracion;
use Barryvdh\DomPDF\Facade\Pdf;

class ReporteController extends Controller
{
    public function general(Request $request)
    {
        $request->validate([
            'fecha_inicio' => 'required|date',
            'fecha_fin'    => 'required|date|after_or_equal:fecha_inicio',
        ]);

        $inicio = $request->fecha_inicio;
        $fin    = $request->fecha_fin;

        // Datos de la tienda para el encabezado
        $config = Configuracion::first();

        // 1. Compras
        $compras = DB::table('compras')
            ->join('proveedores', 'compras.proveedor_id', '=', 'proveedores.id')
            ->whereBetween('compras.fecha_registro', [$inicio, $fin])
            ->select('compras.fecha_registro as fecha', 'proveedores.nombre as proveedor', 'compras.total')
            ->orderBy('compras.fecha_registro')
            ->get()
            ->map(function ($item, $index) {
                $item->nro = $index + 1;
                return $item;
            });

        // 2. Ventas (solo PAGADA)
        $ventas = DB::table('ventas')
            ->join('metodos_pagos', 'ventas.metodo_pago_id', '=', 'metodos_pagos.id')
            ->where('ventas.estado', 'PAGADA')
            ->whereBetween('ventas.fecha', [$inicio, $fin])
            ->select('ventas.correlativo', 'ventas.fecha', 'ventas.total', 'metodos_pagos.nombre as metodo')
            ->orderBy('ventas.fecha')
            ->get()
            ->map(function ($item, $index) {
                $item->nro = $index + 1;
                return $item;
            });

        // 3. Devoluciones (solo DEVUELTA)
        $devoluciones = DB::table('devoluciones_ventas')
            ->join('ventas', 'devoluciones_ventas.venta_id', '=', 'ventas.id')
            ->where('devoluciones_ventas.estado', 'DEVUELTA')
            ->whereBetween('devoluciones_ventas.fecha', [$inicio, $fin])
            ->select('devoluciones_ventas.fecha', 'devoluciones_ventas.motivo', 'devoluciones_ventas.total', 'ventas.correlativo as venta_correlativo')
            ->orderBy('devoluciones_ventas.fecha')
            ->get()
            ->map(function ($item, $index) {
                $item->nro = $index + 1;
                return $item;
            });

        // 4. Productos Dañados
        $daniados = DB::table('productos_daniados')
            ->join('productos', 'productos_daniados.producto_id', '=', 'productos.id')
            ->whereBetween('productos_daniados.fecha', [$inicio, $fin])
            ->select('productos_daniados.fecha', 'productos.nombre as producto', 'productos_daniados.descripcion', 'productos_daniados.cantidad', 'productos_daniados.costo_unitario', 'productos_daniados.total_perdida', 'productos_daniados.estado')
            ->orderBy('productos_daniados.fecha')
            ->get()
            ->map(function ($item, $index) {
                $item->nro = $index + 1;
                return $item;
            });

        // Totales para el resumen
        $totalCompras      = $compras->sum('total');
        $totalVentas       = $ventas->sum('total');
        $totalDevoluciones = $devoluciones->sum('total');
        $totalPerdidas     = $daniados->sum('total_perdida');
        $gananciaNeta      = $totalVentas - $totalCompras - $totalDevoluciones - $totalPerdidas;

        // Pasar a la vista solo las colecciones que tengan datos
        $pdf = Pdf::loadView('reportes.General', compact(
            'config', 'inicio', 'fin',
            'compras', 'ventas', 'devoluciones', 'daniados',
            'totalCompras', 'totalVentas', 'totalDevoluciones', 'totalPerdidas', 'gananciaNeta'
        ));

        $pdf->setPaper('A4', 'portrait');
        return $pdf->stream("reporte-general-{$inicio}-{$fin}.pdf");
    }
}
