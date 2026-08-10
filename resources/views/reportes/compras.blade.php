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
                <div class="reporte-titulo">REPORTE DE COMPRAS</div>
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

    <!-- COMPRAS CON DETALLES -->
    @if($compras->isNotEmpty())
        <div class="seccion-titulo">COMPRAS</div>
        @foreach($compras as $c)
            <table style="margin-bottom: 10px;">
                <thead>
                    <tr>
                        <th>N°</th>
                        <th>Factura</th>
                        <th>Fecha</th>
                        <th>Proveedor</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="text-center">{{ $c->nro }}</td>
                        <td>{{ $c->numero_factura }}</td>
                        <td>{{ $c->fecha }}</td>
                        <td>{{ $c->proveedor }}</td>
                        <td class="text-right">${{ number_format($c->total, 2) }}</td>
                    </tr>
                </tbody>
            </table>
            @if($c->detalles->isNotEmpty())
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
                    @foreach($c->detalles as $d)
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

    <!-- RESUMEN -->
    <table class="resumen-wrapper">
        <tbody>
            <tr>
                <td>
                    <div class="resumen-contenido">
                        <table style="width: 60%; margin-left: auto;">
                            <tr class="total-final">
                                <td><strong>TOTAL COMPRAS</strong></td>
                                <td class="text-right {{ $totalCompras >= 0 ? 'ganancia-positiva' : 'ganancia-negativa' }}">
                                    <strong>${{ number_format($totalCompras, 2) }}</strong>
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
