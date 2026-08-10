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
                <div class="reporte-titulo">REPORTE GENERAL</div>
                <div class="reporte-periodo">Período: {{ $inicio }} al {{ $fin }}</div>
            </td>
        </tr>
    </table>

    @if($compras->isNotEmpty())
        <div class="seccion-titulo">COMPRAS</div>
        <table>
            <thead>
                <tr>
                    <th>N°</th>
                    <th>Fecha</th>
                    <th>Proveedor</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($compras as $c)
                <tr>
                    <td class="text-center">{{ $c->nro }}</td>
                    <td>{{ $c->fecha }}</td>
                    <td>{{ $c->proveedor }}</td>
                    <td class="text-right">${{ number_format($c->total, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if($ventas->isNotEmpty())
        <div class="seccion-titulo">VENTAS</div>
        <table>
            <thead>
                <tr>
                    <th>N°</th>
                    <th>Fecha</th>
                    <th>Correlativo</th>
                    <th>Método</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($ventas as $v)
                <tr>
                    <td class="text-center">{{ $v->nro }}</td>
                    <td>{{ $v->fecha }}</td>
                    <td>{{ $v->correlativo }}</td>
                    <td>{{ $v->metodo }}</td>
                    <td class="text-right">${{ number_format($v->total, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if($devoluciones->isNotEmpty())
        <div class="seccion-titulo">DEVOLUCIONES</div>
        <table>
            <thead>
                <tr>
                    <th>N°</th>
                    <th>Fecha</th>
                    <th>Venta</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($devoluciones as $d)
                <tr>
                    <td class="text-center">{{ $d->nro }}</td>
                    <td>{{ $d->fecha }}</td>
                    <td>{{ $d->venta_correlativo }}</td>
                    <td class="text-right">${{ number_format($d->total, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if($daniados->isNotEmpty())
        <div class="seccion-titulo">PRODUCTOS DAÑADOS</div>
        <table>
            <thead>
                <tr>
                    <th>N°</th>
                    <th>Fecha</th>
                    <th>Producto</th>
                    <th class="text-center">Cant.</th>
                    <th class="text-right">Costo Unit.</th>
                    <th class="text-right">Total Pérdida</th>
                </tr>
            </thead>
            <tbody>
                @foreach($daniados as $pd)
                <tr>
                    <td class="text-center">{{ $pd->nro }}</td>
                    <td>{{ $pd->fecha }}</td>
                    <td>{{ $pd->producto }}</td>
                    <td class="text-center">{{ $pd->cantidad }}</td>
                    <td class="text-right">${{ number_format($pd->costo_unitario, 2) }}</td>
                    <td class="text-right">${{ number_format($pd->total_perdida, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <!-- RESUMEN (bloque atómico, se manda completo a la siguiente página si no cabe) -->
    <table class="resumen-wrapper">
        <tbody>
            <tr>
                <td>
                    <div class="resumen-contenido">
                        <table style="width: 60%; margin-left: auto;">
                            <tr>
                                <td><strong>Total Compras</strong></td>
                                <td class="text-right">${{ number_format($totalCompras, 2) }}</td>
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
                            @if($daniados->isNotEmpty())
                            <tr>
                                <td><strong>Total Pérdidas por Daños</strong></td>
                                <td class="text-right negativo">${{ number_format($totalPerdidas, 2) }}</td>
                            </tr>
                            @endif
                            <tr class="total-final">
                                <td><strong>GANANCIA NETA</strong></td>
                                <td class="text-right {{ $gananciaNeta >= 0 ? 'ganancia-positiva' : 'ganancia-negativa' }}">
                                    <strong>${{ number_format($gananciaNeta, 2) }}</strong>
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
