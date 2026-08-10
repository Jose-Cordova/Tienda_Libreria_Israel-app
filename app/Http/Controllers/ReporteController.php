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
            ->select('devoluciones_ventas.fecha', 'devoluciones_ventas.total', 'ventas.correlativo as venta_correlativo')
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
            ->select('productos_daniados.fecha', 'productos.nombre as producto', 'productos_daniados.cantidad', 'productos_daniados.costo_unitario', 'productos_daniados.total_perdida')
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

        // Generar PDF
        $pdf = Pdf::loadView('reportes.General', compact(
            'config', 'inicio', 'fin',
            'compras', 'ventas', 'devoluciones', 'daniados',
            'totalCompras', 'totalVentas', 'totalDevoluciones', 'totalPerdidas', 'gananciaNeta'
        ));

        // Opciones de DomPDF
        $pdf->getDomPDF()->set_option("isPhpEnabled", true);
        $pdf->getDomPDF()->set_option("isHtml5ParserEnabled", true);
        $pdf->getDomPDF()->set_option("isFontSubsettingEnabled", true);

        $pdf->setPaper('A4', 'portrait');

        // Pie de página con número de página (método nativo, más confiable)
        // Nota: se llama DESPUÉS de setPaper para que el canvas ya tenga
        // las dimensiones correctas de A4 (595 x 842 pt aprox.)
        $pdf->render();

        $canvas = $pdf->getDomPDF()->getCanvas();
        $canvas->page_text(
            270,                          // x: ajustalo si no queda centrado en tu A4
            $canvas->get_height() - 30,   // y: 30pt desde el borde inferior
            "Página {PAGE_NUM} de {PAGE_COUNT}",
            "DejaVu Sans",
            9,
            [0.5, 0.5, 0.5]
        );

        return $pdf->stream("reporte-general-{$inicio}-{$fin}.pdf");
    }


///////////////////////
// REPORTE DE VENTAS//
//////////////////////


public function ventas(Request $request)
{
    $request->validate([
        'fecha_inicio'  => 'required|date',
        'fecha_fin'     => 'required|date|after_or_equal:fecha_inicio',
        'tipo_cliente'  => 'nullable|string|in:DETALLES,MAYORISTA',
        'metodo_pago_id'=> 'nullable|integer|exists:metodos_pagos,id',
        'estado'        => 'nullable|string|in:PAGADA,CREDITO,DEVOLUCION,ANULADA',
    ]);

    $inicio = $request->fecha_inicio;
    $fin    = $request->fecha_fin;

    // --- Filtros activos para mostrar en el reporte ---
    $filtrosActivos = [];
    if ($request->filled('tipo_cliente')) {
        $filtrosActivos['Tipo de cliente'] = $request->tipo_cliente;
    }
    if ($request->filled('metodo_pago_id')) {
        $metodo = DB::table('metodos_pagos')->find($request->metodo_pago_id);
        $filtrosActivos['Método de pago'] = $metodo ? $metodo->nombre : $request->metodo_pago_id;
    }
    if ($request->filled('estado')) {
        $filtrosActivos['Estado'] = $request->estado;
    }

    // Si se filtra por un estado específico, ocultar devoluciones y créditos
    $mostrarDevoluciones = !$request->filled('estado');
    $mostrarCreditos     = !$request->filled('estado');

    $config = Configuracion::first();

    // --- 1. VENTAS con sus detalles ---
    $ventasQuery = DB::table('ventas')
        ->join('metodos_pagos', 'ventas.metodo_pago_id', '=', 'metodos_pagos.id')
        ->whereBetween('ventas.fecha', [$inicio, $fin])
        ->select(
            'ventas.id', 'ventas.correlativo', 'ventas.fecha', 'ventas.total',
            'ventas.tipo_cliente', 'ventas.estado',
            'metodos_pagos.nombre as metodo'
        );

    if ($request->filled('tipo_cliente')) {
        $ventasQuery->where('ventas.tipo_cliente', $request->tipo_cliente);
    }
    if ($request->filled('metodo_pago_id')) {
        $ventasQuery->where('ventas.metodo_pago_id', $request->metodo_pago_id);
    }
    if ($request->filled('estado')) {
        $ventasQuery->where('ventas.estado', $request->estado);
    }

    $ventas = $ventasQuery->orderBy('ventas.fecha')->get();

    // Detalles agrupados por venta
    $ventaIds = $ventas->pluck('id');
    $detallesVentas = DB::table('detalle_ventas')
        ->join('productos', 'detalle_ventas.producto_id', '=', 'productos.id')
        ->leftJoin('lotes', 'detalle_ventas.lote_id', '=', 'lotes.id')
        ->whereIn('detalle_ventas.venta_id', $ventaIds)
        ->select(
            'detalle_ventas.venta_id',
            'productos.nombre as producto',
            'detalle_ventas.cantidad',
            'detalle_ventas.precio_unitario',
            'detalle_ventas.subtotal',
            'lotes.codigo_lote'
        )
        ->orderBy('detalle_ventas.id')
        ->get()
        ->groupBy('venta_id');

    // Mapear ventas con detalles y numerar
    $ventas = $ventas->map(function ($item, $index) use ($detallesVentas) {
        $item->nro = $index + 1;
        $item->detalles = $detallesVentas->get($item->id, collect());
        return $item;
    });

    // --- 2. DEVOLUCIONES (con filtros heredados de ventas) ---
    $devoluciones = collect();
    if ($mostrarDevoluciones) {
        $devolucionesQuery = DB::table('devoluciones_ventas')
            ->join('ventas', 'devoluciones_ventas.venta_id', '=', 'ventas.id')
            ->where('devoluciones_ventas.estado', 'DEVUELTA')
            ->whereBetween('devoluciones_ventas.fecha', [$inicio, $fin]);

        // Aplicar los mismos filtros de ventas a la venta relacionada
        if ($request->filled('tipo_cliente')) {
            $devolucionesQuery->where('ventas.tipo_cliente', $request->tipo_cliente);
        }
        if ($request->filled('metodo_pago_id')) {
            $devolucionesQuery->where('ventas.metodo_pago_id', $request->metodo_pago_id);
        }

        $devoluciones = $devolucionesQuery
            ->select(
                'devoluciones_ventas.id', 'devoluciones_ventas.fecha', 'devoluciones_ventas.total',
                'devoluciones_ventas.motivo',
                'ventas.correlativo as venta_correlativo'
            )
            ->orderBy('devoluciones_ventas.fecha')
            ->get();

        // Detalles de devoluciones
        $devolucionIds = $devoluciones->pluck('id');
        $detallesDevoluciones = DB::table('detalle_devoluciones_ventas')
            ->join('productos', 'detalle_devoluciones_ventas.producto_id', '=', 'productos.id')
            ->join('detalle_ventas', 'detalle_devoluciones_ventas.detalle_venta_id', '=', 'detalle_ventas.id')
            ->leftJoin('lotes', 'detalle_ventas.lote_id', '=', 'lotes.id')
            ->whereIn('detalle_devoluciones_ventas.devolucion_venta_id', $devolucionIds)
            ->select(
                'detalle_devoluciones_ventas.devolucion_venta_id',
                'productos.nombre as producto',
                'detalle_devoluciones_ventas.cantidad',
                'detalle_devoluciones_ventas.condicion',
                'detalle_devoluciones_ventas.precio_unitario',
                'detalle_devoluciones_ventas.subtotal',
                'lotes.codigo_lote'
            )
            ->orderBy('detalle_devoluciones_ventas.id')
            ->get()
            ->groupBy('devolucion_venta_id');

        // Mapear devoluciones con detalles y numerar
        $devoluciones = $devoluciones->map(function ($item, $index) use ($detallesDevoluciones) {
            $item->nro = $index + 1;
            $item->detalles = $detallesDevoluciones->get($item->id, collect());
            return $item;
        });
    }

    // --- 3. CRÉDITOS (con filtros heredados de ventas) ---
    $creditos = collect();
    if ($mostrarCreditos) {
        $creditosQuery = DB::table('creditos')
            ->join('ventas', 'creditos.venta_id', '=', 'ventas.id')
            ->join('clientes_creditos', 'creditos.cliente_credito_id', '=', 'clientes_creditos.id')
            ->whereBetween('ventas.fecha', [$inicio, $fin]);

        // Aplicar los mismos filtros de ventas
        if ($request->filled('tipo_cliente')) {
            $creditosQuery->where('ventas.tipo_cliente', $request->tipo_cliente);
        }
        if ($request->filled('metodo_pago_id')) {
            $creditosQuery->where('ventas.metodo_pago_id', $request->metodo_pago_id);
        }
        if ($request->filled('estado')) {
            // Si se filtra por estado, solo mostrar créditos de ventas en ese estado
            $creditosQuery->where('ventas.estado', $request->estado);
        }

        $creditos = $creditosQuery
            ->select(
                'ventas.correlativo',
                'clientes_creditos.nombre as cliente',
                'clientes_creditos.dui',
                'creditos.monto_adeudado',
                'creditos.saldo',
                'creditos.estado as estado_credito'
            )
            ->orderBy('ventas.correlativo')
            ->get()
            ->map(function ($item, $index) {
                $item->nro = $index + 1;
                return $item;
            });
    }

    // --- 4. TOTALES para el resumen ---
    $totalVentas       = $ventas->sum('total');
    $totalDevoluciones = $devoluciones->sum('total');
    $totalPendiente    = $creditos->sum(function ($c) {
        return $c->monto_adeudado - $c->saldo;
    });
    $cantidadVentas    = $ventas->count();
    $totalFinanciero   = $totalVentas - $totalDevoluciones;

    // --- 5. GENERAR PDF ---
    $pdf = Pdf::loadView('reportes.Ventas', compact(
        'config', 'inicio', 'fin',
        'ventas', 'devoluciones', 'creditos',
        'totalVentas', 'totalDevoluciones', 'totalPendiente',
        'cantidadVentas', 'totalFinanciero', 'filtrosActivos',
        'mostrarDevoluciones', 'mostrarCreditos'
    ));

    $pdf->getDomPDF()->set_option("isPhpEnabled", true);
    $pdf->getDomPDF()->set_option("isHtml5ParserEnabled", true);
    $pdf->getDomPDF()->set_option("isFontSubsettingEnabled", true);
    $pdf->setPaper('A4', 'portrait');
    $pdf->render();

    $canvas = $pdf->getDomPDF()->getCanvas();
    $canvas->page_text(
        270,
        $canvas->get_height() - 30,
        "Página {PAGE_NUM} de {PAGE_COUNT}",
        "DejaVu Sans",
        9,
        [0.5, 0.5, 0.5]
    );

    return $pdf->stream("reporte-ventas-{$inicio}-{$fin}.pdf");
}


///////////////////////
// REPORTE DE COMPRAS//
//////////////////////


public function compras(Request $request)
{
    $request->validate([
        'fecha_inicio' => 'required|date',
        'fecha_fin'    => 'required|date|after_or_equal:fecha_inicio',
        'proveedor_id' => 'nullable|integer|exists:proveedores,id',
    ]);

    $inicio = $request->fecha_inicio;
    $fin    = $request->fecha_fin;

    // Filtros activos
    $filtrosActivos = [];
    if ($request->filled('proveedor_id')) {
        $proveedor = DB::table('proveedores')->find($request->proveedor_id);
        $filtrosActivos['Proveedor'] = $proveedor ? $proveedor->nombre : $request->proveedor_id;
    }

    $config = Configuracion::first();

    // --- 1. Compras con sus detalles ---
    $comprasQuery = DB::table('compras')
        ->join('proveedores', 'compras.proveedor_id', '=', 'proveedores.id')
        ->whereBetween('compras.fecha_registro', [$inicio, $fin])
        ->select(
            'compras.id', 'compras.numero_factura', 'compras.fecha_registro as fecha',
            'compras.total', 'proveedores.nombre as proveedor'
        );

    if ($request->filled('proveedor_id')) {
        $comprasQuery->where('compras.proveedor_id', $request->proveedor_id);
    }

    $compras = $comprasQuery->orderBy('compras.fecha_registro')->get();

    // Detalles agrupados por compra
    $compraIds = $compras->pluck('id');
    $detallesCompras = DB::table('detalle_compras')
        ->join('productos', 'detalle_compras.producto_id', '=', 'productos.id')
        ->leftJoin('lotes', function ($join) {
            $join->on('lotes.compra_id', '=', 'detalle_compras.compra_id')
                 ->on('lotes.producto_id', '=', 'detalle_compras.producto_id');
        })
        ->whereIn('detalle_compras.compra_id', $compraIds)
        ->select(
            'detalle_compras.compra_id',
            'productos.nombre as producto',
            'detalle_compras.cantidad',
            'detalle_compras.precio_unitario',
            'detalle_compras.subtotal',
            'lotes.codigo_lote'
        )
        ->orderBy('detalle_compras.id')
        ->get()
        ->groupBy('compra_id');

    // Mapear compras con detalles y numerar
    $compras = $compras->map(function ($item, $index) use ($detallesCompras) {
        $item->nro = $index + 1;
        $item->detalles = $detallesCompras->get($item->id, collect());
        return $item;
    });

    // --- 2. Totales ---
    $totalCompras = $compras->sum('total');

    // --- 3. Generar PDF ---
    $pdf = Pdf::loadView('reportes.Compras', compact(
        'config', 'inicio', 'fin',
        'compras', 'totalCompras', 'filtrosActivos'
    ));

    $pdf->getDomPDF()->set_option("isPhpEnabled", true);
    $pdf->getDomPDF()->set_option("isHtml5ParserEnabled", true);
    $pdf->getDomPDF()->set_option("isFontSubsettingEnabled", true);
    $pdf->setPaper('A4', 'portrait');
    $pdf->render();

    $canvas = $pdf->getDomPDF()->getCanvas();
    $canvas->page_text(
        270,
        $canvas->get_height() - 30,
        "Página {PAGE_NUM} de {PAGE_COUNT}",
        "DejaVu Sans",
        9,
        [0.5, 0.5, 0.5]
    );

    return $pdf->stream("reporte-compras-{$inicio}-{$fin}.pdf");
}


///////////////////////
// REPORTE DE COMPRAS//
//////////////////////


public function creditos(Request $request)
{
    $request->validate([
        'fecha_inicio'      => 'required|date',
        'fecha_fin'         => 'required|date|after_or_equal:fecha_inicio',
        'cliente_credito_id'=> 'nullable|integer|exists:clientes_creditos,id',
    ]);

    $inicio = $request->fecha_inicio;
    $fin    = $request->fecha_fin;

    // Filtros activos
    $filtrosActivos = [];
    if ($request->filled('cliente_credito_id')) {
        $cliente = DB::table('clientes_creditos')->find($request->cliente_credito_id);
        $filtrosActivos['Cliente'] = $cliente ? $cliente->nombre : $request->cliente_credito_id;
    }

    $config = Configuracion::first();

    // --- Consulta de créditos ---
    $creditosQuery = DB::table('creditos')
        ->join('ventas', 'creditos.venta_id', '=', 'ventas.id')
        ->join('clientes_creditos', 'creditos.cliente_credito_id', '=', 'clientes_creditos.id')
        ->whereBetween('ventas.fecha', [$inicio, $fin])
        ->select(
            'clientes_creditos.id as cliente_id',
            'clientes_creditos.nombre as cliente',
            'clientes_creditos.dui',
            'ventas.correlativo',
            'creditos.monto_adeudado',
            'creditos.saldo',
            'creditos.estado'
        )
        ->orderBy('clientes_creditos.nombre')
        ->orderBy('ventas.correlativo');

    if ($request->filled('cliente_credito_id')) {
        $creditosQuery->where('clientes_creditos.id', $request->cliente_credito_id);
    }

    $creditos = $creditosQuery->get();

    // Agrupar por cliente
    $clientesAgrupados = $creditos->groupBy('cliente_id')->map(function ($creditosCliente, $clienteId) {
        $cliente = $creditosCliente->first();
        return [
            'nombre'   => $cliente->cliente,
            'dui'      => $cliente->dui,
            'creditos' => $creditosCliente->map(function ($item, $index) {
                $item->nro = $index + 1;
                $item->pendiente = $item->monto_adeudado - $item->saldo;
                return $item;
            }),
        ];
    });

    // Totales
    $totalAdeudado  = $creditos->sum('monto_adeudado');
    $totalPendiente = $creditos->sum(function ($c) {
        return $c->monto_adeudado - $c->saldo;
    });
    $cantidadCreditos = $creditos->count();

    // Generar PDF
    $pdf = Pdf::loadView('reportes.Creditos', compact(
        'config', 'inicio', 'fin',
        'clientesAgrupados', 'totalAdeudado', 'totalPendiente',
        'cantidadCreditos', 'filtrosActivos'
    ));

    $pdf->getDomPDF()->set_option("isPhpEnabled", true);
    $pdf->getDomPDF()->set_option("isHtml5ParserEnabled", true);
    $pdf->getDomPDF()->set_option("isFontSubsettingEnabled", true);
    $pdf->setPaper('A4', 'portrait');
    $pdf->render();

    $canvas = $pdf->getDomPDF()->getCanvas();
    $canvas->page_text(
        270,
        $canvas->get_height() - 30,
        "Página {PAGE_NUM} de {PAGE_COUNT}",
        "DejaVu Sans",
        9,
        [0.5, 0.5, 0.5]
    );

    return $pdf->stream("reporte-creditos-{$inicio}-{$fin}.pdf");
}


/////////////////////////////////
// REPORTE DE PRODUCTOS DAÑADOS//
/////////////////////////////////


public function productosDaniados(Request $request)
{
    $request->validate([
        'fecha_inicio' => 'required|date',
        'fecha_fin'    => 'required|date|after_or_equal:fecha_inicio',
        'estado'       => 'nullable|string|in:DEVOLUCION,DANIADO',
    ]);

    $inicio = $request->fecha_inicio;
    $fin    = $request->fecha_fin;

    // Filtros activos
    $filtrosActivos = [];
    if ($request->filled('estado')) {
        $filtrosActivos['Origen'] = $request->estado === 'DEVOLUCION' ? 'Devolución' : 'Manual';
    }

    $config = Configuracion::first();

    // Consulta de productos dañados
    $daniadosQuery = DB::table('productos_daniados')
        ->join('productos', 'productos_daniados.producto_id', '=', 'productos.id')
        ->whereBetween('productos_daniados.fecha', [$inicio, $fin])
        ->select(
            'productos_daniados.id',
            'productos_daniados.fecha',
            'productos.nombre as producto',
            'productos_daniados.descripcion',
            'productos_daniados.cantidad',
            'productos_daniados.costo_unitario',
            'productos_daniados.total_perdida',
            'productos_daniados.estado'
        )
        ->orderBy('productos_daniados.fecha');

    if ($request->filled('estado')) {
        $daniadosQuery->where('productos_daniados.estado', $request->estado);
    }

    $daniados = $daniadosQuery->get()->map(function ($item, $index) {
        $item->nro = $index + 1;
        $item->origen = $item->estado === 'DEVOLUCION' ? 'Devolución' : 'Manual';
        return $item;
    });

    // Totales
    $totalPerdida     = $daniados->sum('total_perdida');
    $totalCantidad    = $daniados->sum('cantidad');
    $cantidadDaniados = $daniados->count();

    // Generar PDF
    $pdf = Pdf::loadView('reportes.ProductosDaniados', compact(
        'config', 'inicio', 'fin',
        'daniados', 'totalPerdida', 'totalCantidad',
        'cantidadDaniados', 'filtrosActivos'
    ));

    $pdf->getDomPDF()->set_option("isPhpEnabled", true);
    $pdf->getDomPDF()->set_option("isHtml5ParserEnabled", true);
    $pdf->getDomPDF()->set_option("isFontSubsettingEnabled", true);
    $pdf->setPaper('A4', 'portrait');
    $pdf->render();

    $canvas = $pdf->getDomPDF()->getCanvas();
    $canvas->page_text(
        270,
        $canvas->get_height() - 30,
        "Página {PAGE_NUM} de {PAGE_COUNT}",
        "DejaVu Sans",
        9,
        [0.5, 0.5, 0.5]
    );

    return $pdf->stream("reporte-productos-daniados-{$inicio}-{$fin}.pdf");
}
}
