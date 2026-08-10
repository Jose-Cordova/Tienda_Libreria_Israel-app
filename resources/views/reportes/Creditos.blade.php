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
                <div class="reporte-titulo">REPORTE DE CRÉDITOS</div>
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

    <!-- CRÉDITOS AGRUPADOS POR CLIENTE -->
    @if(count($clientesAgrupados) > 0)
        @foreach($clientesAgrupados as $cliente)
            <div class="seccion-titulo">{{ $cliente['nombre'] }} (DUI: {{ $cliente['dui'] }})</div>
            <table>
                <thead>
                    <tr>
                        <th>N°</th>
                        <th>Venta</th>
                        <th class="text-right">Monto Adeudado</th>
                        <th class="text-right">Saldo Abonado</th>
                        <th class="text-right">Pendiente</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($cliente['creditos'] as $c)
                    <tr>
                        <td class="text-center">{{ $c->nro }}</td>
                        <td>{{ $c->correlativo }}</td>
                        <td class="text-right">${{ number_format($c->monto_adeudado, 2) }}</td>
                        <td class="text-right">${{ number_format($c->saldo, 2) }}</td>
                        <td class="text-right {{ $c->pendiente > 0 ? 'negativo' : 'positivo' }}">
                            ${{ number_format($c->pendiente, 2) }}
                        </td>
                        <td>{{ $c->estado }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endforeach
    @else
        <div style="text-align: center; padding: 30px; color: #888;">No se encontraron créditos en el período.</div>
    @endif

    <!-- RESUMEN -->
    <table class="resumen-wrapper">
        <tbody>
            <tr>
                <td>
                    <div class="resumen-contenido">
                        <table style="width: 60%; margin-left: auto;">
                            <tr>
                                <td><strong>Cantidad de Créditos</strong></td>
                                <td class="text-right">{{ $cantidadCreditos }}</td>
                            </tr>
                            <tr>
                                <td><strong>Total Adeudado</strong></td>
                                <td class="text-right">${{ number_format($totalAdeudado, 2) }}</td>
                            </tr>
                            <tr class="total-final">
                                <td><strong>TOTAL PENDIENTE</strong></td>
                                <td class="text-right {{ $totalPendiente > 0 ? 'negativo' : 'positivo' }}">
                                    <strong>${{ number_format($totalPendiente, 2) }}</strong>
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
