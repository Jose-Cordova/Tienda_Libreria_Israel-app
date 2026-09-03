<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Configuracion;
use Barryvdh\DomPDF\Facade\Pdf;

class ReporteController extends Controller
{
    /**
     * Agrega el pie de página estándar al PDF.
     */
    private function agregarPiePagina($pdf, $fechaGeneracion = null)
    {
        $canvas = $pdf->getDomPDF()->getCanvas();
        $fechaGeneracion = $fechaGeneracion ?? now()->format('d/m/Y');

        // Fecha de generación a la izquierda
        $canvas->page_text(
            40,
            $canvas->get_height() - 30,
            "Fecha de generación: {$fechaGeneracion}",
            "DejaVu Sans",
            9,
            [0.5, 0.5, 0.5]
        );

        // Número de página a la derecha
        $canvas->page_text(
            $canvas->get_width() - 100,
            $canvas->get_height() - 30,
            "Página {PAGE_NUM} de {PAGE_COUNT}",
            "DejaVu Sans",
            9,
            [0.5, 0.5, 0.5]
        );
    }

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
        $pdf->render();

        // Nuevo pie de página
        $this->agregarPiePagina($pdf);

        return $pdf->stream("reporte-general-{$inicio}-{$fin}.pdf");
    }

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

        // Filtros activos para mostrar en el PDF
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

        $config = Configuracion::first();

        // --- 1. OBTENER VENTAS (sin detalles) ---
        $ventasQuery = DB::table('ventas')
            ->leftJoin('metodos_pagos', 'ventas.metodo_pago_id', '=', 'metodos_pagos.id')
            ->whereBetween('ventas.fecha', [$inicio, $fin])
            ->select(
                'ventas.id',
                'ventas.correlativo',
                'ventas.fecha',
                'ventas.total',
                'ventas.tipo_cliente',
                'ventas.estado',
                DB::raw("COALESCE(metodos_pagos.nombre, 'Crédito') as metodo")
            );

        if ($request->filled('tipo_cliente')) {
            $ventasQuery->where('ventas.tipo_cliente', $request->tipo_cliente);
        }
        if ($request->filled('metodo_pago_id')) {
            $ventasQuery->where('ventas.metodo_pago_id', $request->metodo_pago_id);
        }
        if ($request->filled('estado')) {
            $ventasQuery->where('ventas.estado', $request->estado);
        } else {
            // Por defecto, excluir ventas ANULADA del reporte
            $ventasQuery->where('ventas.estado', '!=', 'ANULADA');
        }

        $ventas = $ventasQuery->orderBy('ventas.fecha')->get();

        // Numeración correlativa para la tabla
        $ventas = $ventas->map(function ($item, $index) {
            $item->nro = $index + 1;
            return $item;
        });

        // --- 2. CALCULAR TOTAL DE VENTAS (excluye ANULADA porque ya no están) ---
        $totalVentas = (float) $ventas->sum('total');

        // --- 3. CALCULAR TOTAL DE DEVOLUCIONES asociadas a las ventas mostradas ---
        $idsVentas = $ventas->pluck('id');
        $totalDevoluciones = 0;
        if ($idsVentas->isNotEmpty()) {
            $totalDevoluciones = (float) DB::table('devoluciones_ventas')
                ->whereIn('venta_id', $idsVentas)
                ->where('devoluciones_ventas.estado', 'DEVUELTA')
                ->sum('devoluciones_ventas.total');
        }

        // --- 4. CALCULAR DINERO PENDIENTE EN CRÉDITOS (solo para ventas CREDITO mostradas) ---
        $idsVentasCredito = $ventas->where('estado', 'CREDITO')->pluck('id');
        $totalPendiente = 0;
        if ($idsVentasCredito->isNotEmpty()) {
            $totalPendiente = (float) DB::table('creditos')
                ->whereIn('creditos.venta_id', $idsVentasCredito)
                ->selectRaw('SUM(creditos.monto_adeudado - creditos.saldo) as pendiente')
                ->value('pendiente');
        }

        // --- 5. TOTALES FINALES ---
        $cantidadVentas = $ventas->count();
        $totalFinanciero = $totalVentas - $totalDevoluciones;
        $mostrarTotalFinanciero = !$request->filled('estado') || $request->estado === 'PAGADA';

        // --- 6. GENERAR PDF ---
        $pdf = Pdf::loadView('reportes.Ventas', compact(
            'config', 'inicio', 'fin',
            'ventas', 'totalVentas', 'totalDevoluciones', 'totalPendiente',
            'cantidadVentas', 'totalFinanciero', 'filtrosActivos', 'mostrarTotalFinanciero'
        ));
        $pdf->getDomPDF()->set_option("isPhpEnabled", true);
        $pdf->getDomPDF()->set_option("isHtml5ParserEnabled", true);
        $pdf->getDomPDF()->set_option("isFontSubsettingEnabled", true);
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        $this->agregarPiePagina($pdf);

        return $pdf->stream("reporte-ventas-{$inicio}-{$fin}.pdf");
    }

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

        $this->agregarPiePagina($pdf);

        return $pdf->stream("reporte-compras-{$inicio}-{$fin}.pdf");
    }

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

        $this->agregarPiePagina($pdf);

        return $pdf->stream("reporte-creditos-{$inicio}-{$fin}.pdf");
    }

    public function productosDaniados(Request $request)
    {
        $request->validate([
            'fecha_inicio' => 'required|date',
            'fecha_fin'    => 'required|date|after_or_equal:fecha_inicio',
            'origen'       => 'nullable|string|in:VENTA,DIRECTO,VENCIMIENTO,PROVEEDOR',
            'estado'       => 'nullable|string|in:REGISTRADO,RECHAZADO,DEVOLUCION,ANULADO',
        ]);

        $inicio = $request->fecha_inicio;
        $fin    = $request->fecha_fin;

        $filtrosActivos = [];
        if ($request->filled('origen')) {
            $filtrosActivos['Origen'] = match($request->origen) {
                'VENTA' => 'Devolución',
                'DIRECTO' => 'Manual',
                'VENCIMIENTO' => 'Vencimiento',
                'PROVEEDOR' => 'Proveedor',
            };
        }
        if ($request->filled('estado')) {
            $filtrosActivos['Estado'] = $request->estado;
        }

        $config = Configuracion::first();

        $daniadosQuery = DB::table('productos_daniados')
            ->join('productos', 'productos_daniados.producto_id', '=', 'productos.id')
            ->leftJoin('lotes', 'productos_daniados.lote_id', '=', 'lotes.id')
            ->whereBetween('productos_daniados.fecha', [$inicio, $fin])
            ->select(
                'productos_daniados.id',
                'productos_daniados.fecha',
                'productos.nombre as producto',
                'productos_daniados.cantidad',
                'productos_daniados.costo_unitario',
                'productos_daniados.total_perdida',
                'productos_daniados.origen',
                'productos_daniados.estado',
                'lotes.codigo_lote as lote'
            )
            ->orderBy('productos_daniados.fecha');

        if ($request->filled('origen')) {
            $daniadosQuery->where('productos_daniados.origen', $request->origen);
        }
        if ($request->filled('estado')) {
            $daniadosQuery->where('productos_daniados.estado', $request->estado);
        }

        $daniados = $daniadosQuery->get()->map(function ($item, $index) {
            $item->nro = $index + 1;
            $item->origen = match($item->origen) {
                'VENTA' => 'Devolución',
                'DIRECTO' => 'Manual',
                'VENCIMIENTO' => 'Vencimiento',
                'PROVEEDOR' => 'Proveedor',
                default => 'Desconocido',
            };
            return $item;
        })->groupBy('origen');

        $totales = [
            'totalPerdida'     => $daniados->flatten(1)->sum('total_perdida'),
            'totalCantidad'    => $daniados->flatten(1)->sum('cantidad'),
            'cantidadDaniados' => $daniados->flatten(1)->count(),
        ];

        $pdf = Pdf::loadView('reportes.ProductosDaniados', compact(
            'config', 'inicio', 'fin',
            'daniados', 'totales', 'filtrosActivos'
        ));

        $pdf->getDomPDF()->set_option("isPhpEnabled", true);
        $pdf->getDomPDF()->set_option("isHtml5ParserEnabled", true);
        $pdf->getDomPDF()->set_option("isFontSubsettingEnabled", true);
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        $this->agregarPiePagina($pdf);

        return $pdf->stream("reporte-productos-daniados-{$inicio}-{$fin}.pdf");
    }

    public function inventario(Request $request)
    {
        $request->validate([
            'seccion' => 'nullable|string|in:TIENDA,LIBRERIA,MEDICAMENTO',
            'marca_id'     => 'nullable|integer|exists:marcas,id',
            'categoria_id' => 'nullable|integer|exists:categorias,id',
            'estado'       => 'nullable|string|in:ACTIVO,INACTIVO',
        ]);

        // Filtros activos
        $filtrosActivos = [];
        if ($request->filled('seccion')) {
            $filtrosActivos['Sección'] = $request->seccion;
        }
        if ($request->filled('marca_id')) {
            $marca = DB::table('marcas')->find($request->marca_id);
            $filtrosActivos['Marca'] = $marca ? $marca->nombre : $request->marca_id;
        }
        if ($request->filled('categoria_id')) {
            $categoria = DB::table('categorias')->find($request->categoria_id);
            $filtrosActivos['Categoría'] = $categoria ? $categoria->nombre : $request->categoria_id;
        }
        if ($request->filled('estado')) {
            $filtrosActivos['Estado'] = $request->estado;
        }

        $config = Configuracion::first();

        // Consulta de inventario
        $productosQuery = DB::table('productos')
            ->join('marcas', 'productos.marca_id', '=', 'marcas.id')
            ->join('categorias', 'productos.categoria_id', '=', 'categorias.id')
            ->select(
                'productos.id',
                'productos.nombre',
                'productos.seccion',
                'marcas.nombre as marca',
                'categorias.nombre as categoria',
                'productos.stock',
                'productos.stock_minimo',
                'productos.precio_detalle',
                'productos.precio_mayor',
                'productos.perecedero',
                'productos.estado'
            )
            ->orderBy('productos.seccion')
            ->orderBy('productos.nombre');

        if ($request->filled('seccion')) {
            $productosQuery->where('productos.seccion', $request->seccion);
        }
        if ($request->filled('marca_id')) {
            $productosQuery->where('productos.marca_id', $request->marca_id);
        }
        if ($request->filled('categoria_id')) {
            $productosQuery->where('productos.categoria_id', $request->categoria_id);
        }
        if ($request->filled('estado')) {
            $productosQuery->where('productos.estado', $request->estado);
        }

        $productos = $productosQuery->get()->map(function ($item, $index) {
            $item->nro = $index + 1;
            $item->stock_bajo = $item->stock <= $item->stock_minimo;
            return $item;
        });

        // Totales
        $totalProductos  = $productos->count();
        $totalStock      = $productos->sum('stock');
        $productosBajos  = $productos->where('stock_bajo', true)->count();

        // Generar PDF
        $pdf = Pdf::loadView('reportes.Inventario', compact(
            'config',
            'productos', 'totalProductos', 'totalStock', 'productosBajos',
            'filtrosActivos'
        ));

        $pdf->getDomPDF()->set_option("isPhpEnabled", true);
        $pdf->getDomPDF()->set_option("isHtml5ParserEnabled", true);
        $pdf->getDomPDF()->set_option("isFontSubsettingEnabled", true);
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        $this->agregarPiePagina($pdf);

        return $pdf->stream("reporte-inventario.pdf");
    }

    public function cierreDiario(Request $request)
    {
        $request->validate([
            'fecha' => 'required|date',
        ]);

        $fecha = $request->fecha;
        $config = Configuracion::first();

        // --- 1. Ventas en efectivo ---
        $efectivo = DB::table('ventas')
            ->join('metodos_pagos', 'ventas.metodo_pago_id', '=', 'metodos_pagos.id')
            ->leftJoin('users', 'ventas.user_id', '=', 'users.id')
            ->whereDate('ventas.fecha', $fecha)
            ->where('metodos_pagos.nombre', 'EFECTIVO')
            ->select(
                'ventas.correlativo',
                'ventas.fecha',
                'ventas.total',
                DB::raw("COALESCE(users.name, 'Sin vendedor') as vendedor")
            )
            ->orderBy('ventas.fecha')
            ->get()
            ->map(function ($item, $index) {
                $item->nro = $index + 1;
                $item->hora = date('H:i:s', strtotime($item->fecha));
                return $item;
            });

        // --- 2. Ventas por transferencia ---
        $transferencia = DB::table('ventas')
            ->join('metodos_pagos', 'ventas.metodo_pago_id', '=', 'metodos_pagos.id')
            ->leftJoin('users', 'ventas.user_id', '=', 'users.id')
            ->whereDate('ventas.fecha', $fecha)
            ->where('metodos_pagos.nombre', 'TRANSFERENCIA')
            ->select(
                'ventas.correlativo',
                'ventas.fecha',
                'ventas.total',
                DB::raw("COALESCE(users.name, 'Sin vendedor') as vendedor")
            )
            ->orderBy('ventas.fecha')
            ->get()
            ->map(function ($item, $index) {
                $item->nro = $index + 1;
                $item->hora = date('H:i:s', strtotime($item->fecha));
                return $item;
            });

        // --- 3. Ventas al crédito ---
        $credito = DB::table('ventas')
            ->leftJoin('creditos', 'ventas.id', '=', 'creditos.venta_id')
            ->leftJoin('clientes_creditos', 'creditos.cliente_credito_id', '=', 'clientes_creditos.id')
            ->leftJoin('users', 'ventas.user_id', '=', 'users.id')
            ->whereDate('ventas.fecha', $fecha)
            ->where('ventas.estado', 'CREDITO')
            ->select(
                'ventas.correlativo',
                'ventas.fecha',
                'ventas.total',
                'clientes_creditos.nombre as cliente',
                'creditos.monto_adeudado',
                DB::raw("COALESCE(users.name, 'Sin vendedor') as vendedor")
            )
            ->orderBy('ventas.fecha')
            ->get()
            ->map(function ($item, $index) {
                $item->nro = $index + 1;
                $item->hora = date('H:i:s', strtotime($item->fecha));
                return $item;
            });

        // --- 4. Devoluciones del día ---
        $devoluciones = DB::table('devoluciones_ventas')
            ->join('ventas', 'devoluciones_ventas.venta_id', '=', 'ventas.id')
            ->where('devoluciones_ventas.estado', 'DEVUELTA')
            ->whereDate('devoluciones_ventas.fecha', $fecha)
            ->select(
                'devoluciones_ventas.fecha',
                'devoluciones_ventas.total',
                'devoluciones_ventas.motivo',
                'ventas.correlativo as venta_correlativo'
            )
            ->orderBy('devoluciones_ventas.fecha')
            ->get()
            ->map(function ($item, $index) {
                $item->nro = $index + 1;
                return $item;
            });

        // --- Totales ---
        $totalEfectivo      = $efectivo->sum('total');
        $totalTransferencia = $transferencia->sum('total');
        $totalCredito       = $credito->sum('total');
        $totalDevoluciones  = $devoluciones->sum('total');
        $totalVentas        = $totalEfectivo + $totalTransferencia + $totalCredito;
        $totalNeto          = $totalVentas - $totalDevoluciones;

        // Generar PDF
        $pdf = Pdf::loadView('reportes.CierreDiario', compact(
            'config', 'fecha',
            'efectivo', 'transferencia', 'credito', 'devoluciones',
            'totalEfectivo', 'totalTransferencia', 'totalCredito',
            'totalDevoluciones', 'totalVentas', 'totalNeto'
        ));

        $pdf->getDomPDF()->set_option("isPhpEnabled", true);
        $pdf->getDomPDF()->set_option("isHtml5ParserEnabled", true);
        $pdf->getDomPDF()->set_option("isFontSubsettingEnabled", true);
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        $this->agregarPiePagina($pdf);

        return $pdf->stream("cierre-diario-{$fecha}.pdf");
    }

    public function cambioProducto(Request $request)
    {
        $request->validate([
            'fecha_inicio' => 'required|date',
            'fecha_fin'    => 'required|date|after_or_equal:fecha_inicio',
            'estado'       => 'nullable|string|in:PENDIENTE,ACEPTADO,RECHAZADO,ANULADO',
        ]);

        $inicio = $request->fecha_inicio;
        $fin    = $request->fecha_fin;

        $filtrosActivos = [];
        if ($request->filled('estado')) {
            $filtrosActivos['Estado'] = $request->estado;
        }

        $config = Configuracion::first();

        $cambiosQuery = DB::table('cambios_productos')
            ->join('productos', 'cambios_productos.producto_id', '=', 'productos.id')
            ->leftJoin('productos as pr', 'cambios_productos.producto_reemplazo_id', '=', 'pr.id')
            ->leftJoin('lotes', 'cambios_productos.lote_id', '=', 'lotes.id')
            ->whereBetween('cambios_productos.fecha', [$inicio, $fin])
            ->select(
                'cambios_productos.id',
                'cambios_productos.fecha',
                'productos.nombre as producto',
                'cambios_productos.cantidad',
                'cambios_productos.costo_unitario',
                'cambios_productos.total_perdida',
                'cambios_productos.estado',
                'lotes.codigo_lote as lote',
                'pr.nombre as producto_reemplazo',
                DB::raw("CAST(CASE WHEN cambios_productos.producto_reemplazo_id IS NOT NULL THEN 1 ELSE 0 END AS INTEGER) as tiene_reemplazo")
            )
            ->orderBy('cambios_productos.fecha');

        if ($request->filled('estado')) {
            $cambiosQuery->where('cambios_productos.estado', $request->estado);
        }

        $cambios = $cambiosQuery->get()->map(function ($item, $index) {
            $item->nro = $index + 1;
            return $item;
        });

        $agrupados = $cambios->groupBy('estado')->map(function ($grupoEstado) {
            return $grupoEstado->groupBy(function ($item) {
                return (int) $item->tiene_reemplazo;
            });
        });

        $totales = [
            'cantidadTotal'          => $cambios->count(),
            'totalCantidad'          => $cambios->sum('cantidad'),
            'cantidadConReemplazo'   => $cambios->where('tiene_reemplazo', 1)->sum('cantidad'),
            'cantidadSinReemplazo'   => $cambios->where('tiene_reemplazo', 0)->count(),
            'totalPerdida'           => $cambios->where('estado', 'RECHAZADO')->sum('total_perdida'),
            'cantidadPendientes'     => $cambios->where('estado', 'PENDIENTE')->count(),
            'totalPendienteCantidad' => $cambios->where('estado', 'PENDIENTE')->sum('cantidad'),
            'totalPendienteCosto'    => $cambios->where('estado', 'PENDIENTE')->sum('total_perdida'),
            'cantidadAceptados'      => $cambios->where('estado', 'ACEPTADO')->count(),
            'cantidadRechazados'     => $cambios->where('estado', 'RECHAZADO')->count(),
            'cantidadAnulados'       => $cambios->where('estado', 'ANULADO')->count(),
        ];

        $pdf = Pdf::loadView('reportes.CambioProducto', compact(
            'config', 'inicio', 'fin',
            'agrupados', 'totales', 'filtrosActivos'
        ));

        $pdf->getDomPDF()->set_option("isPhpEnabled", true);
        $pdf->getDomPDF()->set_option("isHtml5ParserEnabled", true);
        $pdf->getDomPDF()->set_option("isFontSubsettingEnabled", true);
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        $this->agregarPiePagina($pdf);

        return $pdf->stream("reporte-cambio-producto-{$inicio}-{$fin}.pdf");
    }

    public function devolucionesVentas(Request $request)
    {
        $request->validate([
            'fecha_inicio' => 'required|date',
            'fecha_fin'    => 'required|date|after_or_equal:fecha_inicio',
            'estado'       => 'nullable|string|in:DEVUELTA,ANULADA,PERFECTO,DANIADO',
        ]);

        $inicio = $request->fecha_inicio;
        $fin    = $request->fecha_fin;
        $estado = $request->estado;

        $filtrosActivos = [];
        if ($request->filled('estado')) {
            $filtrosActivos['Estado'] = $request->estado === 'DANIADO' ? 'DAÑADO' : $request->estado;
        }

        $config = Configuracion::first();

        $detallesQuery = DB::table('detalle_devoluciones_ventas')
            ->join('devoluciones_ventas', 'detalle_devoluciones_ventas.devolucion_venta_id', '=', 'devoluciones_ventas.id')
            ->join('ventas', 'devoluciones_ventas.venta_id', '=', 'ventas.id')
            ->join('productos', 'detalle_devoluciones_ventas.producto_id', '=', 'productos.id')
            ->leftJoin('lotes', 'detalle_devoluciones_ventas.detalle_venta_id', '=', 'lotes.id')
            ->whereBetween('devoluciones_ventas.fecha', [$inicio, $fin])
            ->select(
                'devoluciones_ventas.id as devolucion_id',
                'devoluciones_ventas.fecha',
                'devoluciones_ventas.estado as estado_devolucion',
                'ventas.correlativo as venta_correlativo',
                'productos.nombre as producto',
                'detalle_devoluciones_ventas.cantidad',
                'detalle_devoluciones_ventas.precio_unitario',
                'detalle_devoluciones_ventas.subtotal',
                'detalle_devoluciones_ventas.condicion',
                'lotes.codigo_lote as lote'
            );

        if ($estado === 'DEVUELTA' || $estado === 'ANULADA') {
            $detallesQuery->where('devoluciones_ventas.estado', $estado);
        } elseif ($estado === 'PERFECTO' || $estado === 'DANIADO') {
            $detallesQuery->where('detalle_devoluciones_ventas.condicion', $estado);
        }

        $detalles = $detallesQuery->orderBy('devoluciones_ventas.fecha')->get();

        $perfectos = $detalles->where('condicion', 'PERFECTO')->values()->map(function ($item, $index) {
            $item->nro = $index + 1;
            return $item;
        });
        $danados = $detalles->where('condicion', 'DANIADO')->values()->map(function ($item, $index) {
            $item->nro = $index + 1;
            return $item;
        });

        $todos = collect();
        if ($estado === 'DEVUELTA' || $estado === 'ANULADA') {
            $todos = $detalles->map(function ($item, $index) {
                $item->nro = $index + 1;
                return $item;
            });
        }

        if ($estado === 'DEVUELTA' || $estado === 'ANULADA') {
            $totalRegistros = DB::table('devoluciones_ventas')
                ->whereBetween('fecha', [$inicio, $fin])
                ->where('estado', $estado)
                ->count();
        } elseif ($estado === 'PERFECTO' || $estado === 'DANIADO') {
            $totalRegistros = $detalles->unique('devolucion_id')->count();
        } else {
            $totalRegistros = DB::table('devoluciones_ventas')
                ->whereBetween('fecha', [$inicio, $fin])
                ->count();
        }

        $cantidadPerfectos = $perfectos->sum('cantidad');
        $cantidadDanados   = $danados->sum('cantidad');
        $totalPerfectos    = $perfectos->sum('subtotal');
        $totalDanados      = $danados->sum('subtotal');

        $pdf = Pdf::loadView('reportes.Devoluciones', compact(
            'config', 'inicio', 'fin',
            'estado', 'perfectos', 'danados', 'todos',
            'totalRegistros', 'cantidadPerfectos', 'cantidadDanados',
            'totalPerfectos', 'totalDanados', 'filtrosActivos'
        ));

        $pdf->getDomPDF()->set_option("isPhpEnabled", true);
        $pdf->getDomPDF()->set_option("isHtml5ParserEnabled", true);
        $pdf->getDomPDF()->set_option("isFontSubsettingEnabled", true);
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        $this->agregarPiePagina($pdf);

        return $pdf->stream("reporte-devoluciones-ventas-{$inicio}-{$fin}.pdf");
    }
}
