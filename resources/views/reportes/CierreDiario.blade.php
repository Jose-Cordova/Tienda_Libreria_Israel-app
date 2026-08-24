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
                <div class="reporte-titulo">CIERRE DIARIO</div>
                <div class="reporte-periodo">Fecha: {{ $fecha }}</div>
            </td>
        </tr>
    </table>

    <!-- 1. VENTAS EN EFECTIVO -->
    @if($efectivo->isNotEmpty())
        <div class="seccion-titulo">EFECTIVO</div>
        <table>
            <thead>
                <tr>
                    <th class="text-center">N°</th>
                    <th>Correlativo</th>
                    <th>Hora</th>
                    <th>Vendedor</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($efectivo as $e)
                <tr>
                    <td class="text-center">{{ $e->nro }}</td>
                    <td>{{ $e->correlativo }}</td>
                    <td>{{ $e->hora }}</td>
                    <td>{{ $e->vendedor }}</td>
                    <td class="text-right">${{ number_format($e->total, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <!-- 2. VENTAS POR TRANSFERENCIA -->
    @if($transferencia->isNotEmpty())
        <div class="seccion-titulo">TRANSFERENCIA</div>
        <table>
            <thead>
                <tr>
                    <th class="text-center">N°</th>
                    <th>Correlativo</th>
                    <th>Hora</th>
                    <th>Vendedor</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($transferencia as $t)
                <tr>
                    <td class="text-center">{{ $t->nro }}</td>
                    <td>{{ $t->correlativo }}</td>
                    <td>{{ $t->hora }}</td>
                    <td>{{ $t->vendedor }}</td>
                    <td class="text-right">${{ number_format($t->total, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <!-- 3. VENTAS AL CRÉDITO -->
    @if($credito->isNotEmpty())
        <div class="seccion-titulo">CRÉDITO</div>
        <table>
            <thead>
                <tr>
                    <th class="text-center">N°</th>
                    <th>Correlativo</th>
                    <th>Hora</th>
                    <th>Vendedor</th>
                    <th>Cliente</th>
                    <th class="text-right">Monto Adeudado</th>
                </tr>
            </thead>
            <tbody>
                @foreach($credito as $c)
                <tr>
                    <td class="text-center">{{ $c->nro }}</td>
                    <td>{{ $c->correlativo }}</td>
                    <td>{{ $c->hora }}</td>
                    <td>{{ $c->vendedor }}</td>
                    <td>{{ $c->cliente ?? 'N/A' }}</td>
                    <td class="text-right">${{ number_format($c->monto_adeudado ?? $c->total, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <!-- 4. DEVOLUCIONES -->
    @if($devoluciones->isNotEmpty())
        <div class="seccion-titulo">DEVOLUCIONES</div>
        <table>
            <thead>
                <tr>
                    <th class="text-center">N°</th>
                    <th>Venta</th>
                    <th>Motivo</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($devoluciones as $d)
                <tr>
                    <td class="text-center">{{ $d->nro }}</td>
                    <td>{{ $d->venta_correlativo }}</td>
                    <td>{{ $d->motivo }}</td>
                    <td class="text-right">${{ number_format($d->total, 2) }}</td>
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
                            @if($efectivo->isNotEmpty())
                            <tr>
                                <td><strong>Total Efectivo</strong></td>
                                <td class="text-right">${{ number_format($totalEfectivo, 2) }}</td>
                            </tr>
                            @endif
                            @if($transferencia->isNotEmpty())
                            <tr>
                                <td><strong>Total Transferencia</strong></td>
                                <td class="text-right">${{ number_format($totalTransferencia, 2) }}</td>
                            </tr>
                            @endif
                            @if($credito->isNotEmpty())
                            <tr>
                                <td><strong>Total Crédito</strong></td>
                                <td class="text-right">${{ number_format($totalCredito, 2) }}</td>
                            </tr>
                            @endif
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
                            <tr class="total-final">
                                <td><strong>TOTAL NETO</strong></td>
                                <td class="text-right {{ $totalNeto >= 0 ? 'ganancia-positiva' : 'ganancia-negativa' }}">
                                    <strong>${{ number_format($totalNeto, 2) }}</strong>
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
