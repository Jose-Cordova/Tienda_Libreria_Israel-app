<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    @include('reportes.css.Pdf')
</head>
<body>
    <!-- ENCABEZADO -->
    <table class="header-table">
        <tr>
            <td>
                <div class="empresa">{{ $config->nombre_tienda }}</div>
                <div class="empresa-detalle">
                    Tel: {{ $config->telefono }} | Email: {{ $config->email }}
                </div>
            </td>
            <td class="reporte-info">
                <div class="reporte-titulo">REPORTE DE VENTAS</div>
                <div class="reporte-periodo">Período: {{ $inicio }} al {{ $fin }}</div>
            </td>
        </tr>
    </table>

    <!-- FILTROS ACTIVOS -->
    @if(count($filtrosActivos) > 0)
        <div style="font-size: 10px; color: #555; margin-bottom: 10px;">
            Filtros aplicados:
            @foreach($filtrosActivos as $nombre => $valor)
                <strong>{{ $nombre }}:</strong> {{ $valor }}@if(!$loop->last) | @endif
            @endforeach
        </div>
    @endif

    <!-- 1. VENTAS -->
    @if($ventas->isNotEmpty())
        <div class="seccion-titulo">VENTAS</div>
        @foreach($ventas as $v)
            <table style="margin-bottom: 10px;">
                <thead>
                    <tr>
                        <th>N°</th>
                        <th>Correlativo</th>
                        <th>Fecha</th>
                        <th>Tipo</th>
                        <th>Método</th>
                        <th>Estado</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="text-center">{{ $v->nro }}</td>
                        <td>{{ $v->correlativo }}</td>
                        <td>{{ $v->fecha }}</td>
                        <td>{{ $v->tipo_cliente }}</td>
                        <td>{{ $v->metodo }}</td>
                        <td>{{ $v->estado }}</td>
                        <td class="text-right">${{ number_format($v->total, 2) }}</td>
                    </tr>
                </tbody>
            </table>

            <!-- DETALLES: solo si $incluirDetalles es true -->
            @if($incluirDetalles && $v->detalles->isNotEmpty())
            <table style="margin-top: -10px; margin-bottom: 15px; width: 95%; margin-left: 5%;">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th class="text-center">Cant.</th>
                        <th class="text-right">P. Unit.</th>
                        <th class="text-right">Subtotal</th>
                        <th>Lote</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($v->detalles as $d)
                    <tr>
                        <td>{{ $d->producto }}</td>
                        <td class="text-center">{{ $d->cantidad }}</td>
                        <td class="text-right">${{ number_format($d->precio_unitario, 2) }}</td>
                        <td class="text-right">${{ number_format($d->subtotal, 2) }}</td>
                        <td>{{ $d->codigo_lote ?? '-' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        @endforeach
    @endif

    <!-- 2. DEVOLUCIONES -->
    @if($mostrarDevoluciones && $devoluciones->isNotEmpty())
        <div class="seccion-titulo">DEVOLUCIONES</div>
        @foreach($devoluciones as $d)
            <table style="margin-bottom: 10px;">
                <thead>
                    <tr>
                        <th>N°</th>
                        <th>Fecha</th>
                        <th>Venta</th>
                        <th>Motivo</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="text-center">{{ $d->nro }}</td>
                        <td>{{ $d->fecha }}</td>
                        <td>{{ $d->venta_correlativo }}</td>
                        <td>{{ $d->motivo }}</td>
                        <td class="text-right">${{ number_format($d->total, 2) }}</td>
                    </tr>
                </tbody>
            </table>
            @if($d->detalles->isNotEmpty())
            <table style="margin-top: -10px; margin-bottom: 15px; width: 95%; margin-left: 5%;">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th class="text-center">Cant.</th>
                        <th>Condición</th>
                        <th class="text-right">P. Unit.</th>
                        <th class="text-right">Subtotal</th>
                        <th>Lote</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($d->detalles as $dd)
                    <tr>
                        <td>{{ $dd->producto }}</td>
                        <td class="text-center">{{ $dd->cantidad }}</td>
                        <td>{{ $dd->condicion }}</td>
                        <td class="text-right">${{ number_format($dd->precio_unitario, 2) }}</td>
                        <td class="text-right">${{ number_format($dd->subtotal, 2) }}</td>
                        <td>{{ $dd->codigo_lote ?? '-' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        @endforeach
    @endif

    <!-- 3. CRÉDITOS -->
    @if($mostrarCreditos && $creditos->isNotEmpty())
        <div class="seccion-titulo">CRÉDITOS</div>
        <table>
            <thead>
                <tr>
                    <th>N°</th>
                    <th>Venta</th>
                    <th>Cliente</th>
                    <th>DUI</th>
                    <th class="text-right">Monto Adeudado</th>
                    <th class="text-right">Saldo Abonado</th>
                    <th class="text-right">Pendiente</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                @foreach($creditos as $c)
                <tr>
                    <td class="text-center">{{ $c->nro }}</td>
                    <td>{{ $c->correlativo }}</td>
                    <td>{{ $c->cliente }}</td>
                    <td>{{ $c->dui }}</td>
                    <td class="text-right">${{ number_format($c->monto_adeudado, 2) }}</td>
                    <td class="text-right">${{ number_format($c->saldo, 2) }}</td>
                    <td class="text-right {{ ($c->monto_adeudado - $c->saldo) > 0 ? 'negativo' : 'positivo' }}">
                        ${{ number_format($c->monto_adeudado - $c->saldo, 2) }}
                    </td>
                    <td>{{ $c->estado_credito }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <!-- RESUMEN -->
    <table class="resumen-wrapper">
        <tbody>
            <tr>
                <td>
                    <div class="resumen-contenido">
                        <table style="width: 60%; margin-left: auto;">
                            <tr>
                                <td><strong>Cantidad de Ventas</strong></td>
                                <td class="text-right">{{ $cantidadVentas }}</td>
                            </tr>
                            <tr>
                                <td><strong>Total Ventas</strong></td>
                                <td class="text-right">${{ number_format($totalVentas, 2) }}</td>
                            </tr>
                            @if($devoluciones->isNotEmpty())
                            <tr>
                                <td><strong>Total Devoluciones</strong></td>
                                <td class="text-right negativo">${{ number_format($totalDevoluciones, 2) }}</td>
                            </tr>
                            @endif
                            @if($creditos->isNotEmpty())
                            <tr>
                                <td><strong>Dinero Pendiente en Créditos</strong></td>
                                <td class="text-right alerta">${{ number_format($totalPendiente, 2) }}</td>
                            </tr>
                            @endif
                            <tr class="total-final">
                                <td><strong>TOTAL FINANCIERO</strong></td>
                                <td class="text-right {{ $totalFinanciero >= 0 ? 'ganancia-positiva' : 'ganancia-negativa' }}">
                                    <strong>${{ number_format($totalFinanciero, 2) }}</strong>
                                </td>
                            </tr>
                        </table>
                    </div>
                </td>
            </tr>
        </tbody>
    </table>
</body>
</html>
