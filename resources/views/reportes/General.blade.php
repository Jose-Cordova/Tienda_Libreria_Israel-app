<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; margin: 20px; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { margin: 0; font-size: 18px; }
        .header p { margin: 2px 0; font-size: 11px; }
        .report-title { font-size: 14px; font-weight: bold; margin: 15px 0 10px 0; border-bottom: 1px solid #000; padding-bottom: 5px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 11px; }
        th, td { border: 1px solid #000; padding: 4px 6px; text-align: left; }
        th { background-color: #e0e0e0; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .summary { margin-top: 30px; border-top: 2px solid #000; padding-top: 10px; }
        .summary table { width: 60%; margin-left: auto; }
        .summary td { border: none; padding: 3px 8px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $config->nombre_tienda }}</h1>
        <p>Tel: {{ $config->telefono }} | Email: {{ $config->email }}</p>
        <h2>REPORTE GENERAL</h2>
        <p>Período: {{ $inicio }} al {{ $fin }}</p>
    </div>

    @if($compras->isNotEmpty())
        <div class="report-title">COMPRAS</div>
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
        <div class="report-title">VENTAS</div>
        <table>
            <thead>
                <tr>
                    <th>N°</th>
                    <th>Correlativo</th>
                    <th>Fecha</th>
                    <th>Método</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($ventas as $v)
                <tr>
                    <td class="text-center">{{ $v->nro }}</td>
                    <td>{{ $v->correlativo }}</td>
                    <td>{{ $v->fecha }}</td>
                    <td>{{ $v->metodo }}</td>
                    <td class="text-right">${{ number_format($v->total, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if($devoluciones->isNotEmpty())
        <div class="report-title">DEVOLUCIONES</div>
        <table>
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
                @foreach($devoluciones as $d)
                <tr>
                    <td class="text-center">{{ $d->nro }}</td>
                    <td>{{ $d->fecha }}</td>
                    <td>{{ $d->venta_correlativo }}</td>
                    <td>{{ $d->motivo }}</td>
                    <td class="text-right">${{ number_format($d->total, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if($daniados->isNotEmpty())
        <div class="report-title">PRODUCTOS DAÑADOS</div>
        <table>
            <thead>
                <tr>
                    <th>N°</th>
                    <th>Fecha</th>
                    <th>Producto</th>
                    <th>Descripción</th>
                    <th class="text-center">Cant.</th>
                    <th class="text-right">Costo Unit.</th>
                    <th class="text-right">Total Pérdida</th>
                    <th>Origen</th>
                </tr>
            </thead>
            <tbody>
                @foreach($daniados as $pd)
                <tr>
                    <td class="text-center">{{ $pd->nro }}</td>
                    <td>{{ $pd->fecha }}</td>
                    <td>{{ $pd->producto }}</td>
                    <td>{{ $pd->descripcion }}</td>
                    <td class="text-center">{{ $pd->cantidad }}</td>
                    <td class="text-right">${{ number_format($pd->costo_unitario, 2) }}</td>
                    <td class="text-right">${{ number_format($pd->total_perdida, 2) }}</td>
                    <td>{{ $pd->estado === 'DEVOLUCION' ? 'Devolución' : 'Manual' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="summary">
        <table>
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
                <td class="text-right">${{ number_format($totalDevoluciones, 2) }}</td>
            </tr>
            @endif
            @if($daniados->isNotEmpty())
            <tr>
                <td><strong>Total Pérdidas por Daños</strong></td>
                <td class="text-right">${{ number_format($totalPerdidas, 2) }}</td>
            </tr>
            @endif
            <tr style="border-top: 1px solid #000; font-size: 13px;">
                <td><strong>GANANCIA NETA</strong></td>
                <td class="text-right"><strong>${{ number_format($gananciaNeta, 2) }}</strong></td>
            </tr>
        </table>
    </div>
</body>
</html>
