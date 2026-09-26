<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    @include('reportes.css.Pdf')

    {{-- ✅ Estilos específicos para el Reporte de Ventas (sobrescriben el CSS compartido) --}}
    <style>
        .header-table {
            padding-bottom: 14px;
            margin-bottom: 26px;
        }
        .empresa {
            font-size: 20px;
        }
        .reporte-titulo {
            font-size: 17px;
            letter-spacing: 1.2px;
        }
        .reporte-periodo {
            font-size: 11px;
            margin-top: 4px;
        }
        .seccion-titulo {
            font-size: 13px;
            padding: 8px 12px;
            margin-top: 24px;
            letter-spacing: 1px;
        }
        th {
            font-size: 11px;
            padding: 7px 9px;
        }
        td {
            font-size: 11px;
            padding: 7px 9px;
        }
    </style>
</head>
<body>
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

    @if(count($filtrosActivos) > 0)
        <div style="font-size: 10px; color: #555; margin-bottom: 10px;">
            Filtros aplicados:
            @foreach($filtrosActivos as $nombre => $valor)
                <strong>{{ $nombre }}:</strong> {{ $valor }}@if(!$loop->last) | @endif
            @endforeach
        </div>
    @endif

    @if($ventas->isNotEmpty())
        <div class="seccion-titulo">VENTAS</div>
        <table>
            <thead>
                <tr>
                    <th class="text-center">N°</th>
                    <th>Correlativo</th>
                    <th>Fecha</th>
                    <th>Tipo</th>
                    <th>Método</th>
                    <th>Estado</th>
                    <th class="text-right">Total</th>
                    <th class="text-right">Devolución</th>
                    <th class="text-right">Neto</th>
                </tr>
            </thead>
            <tbody>
                @foreach($ventas as $v)
                <tr>
                    <td class="text-center">{{ $v->nro }}</td>
                    <td>{{ $v->correlativo }}</td>
                    <td>{{ $v->fecha }}</td>
                    <td>{{ $v->tipo_cliente }}</td>
                    <td>{{ $v->metodo }}</td>
                    <td>{{ $v->estado }}</td>
                    <td class="text-right">${{ number_format($v->total, 2) }}</td>
                    <td class="text-right {{ $v->total_devolucion > 0 ? 'negativo' : '' }}">
                        {{ $v->total_devolucion > 0 ? '$' . number_format($v->total_devolucion, 2) : '-' }}
                    </td>
                    <td class="text-right">${{ number_format($v->total_neto, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p style="text-align:center; color:#888;">No se encontraron ventas para los filtros seleccionados.</p>
    @endif

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
                            @if($totalDevoluciones > 0)
                            <tr>
                                <td><strong>Total Devoluciones</strong></td>
                                <td class="text-right negativo">${{ number_format($totalDevoluciones, 2) }}</td>
                            </tr>
                            @endif
                            @if($totalPendiente > 0)
                            <tr>
                                <td><strong>Dinero Pendiente en Créditos</strong></td>
                                <td class="text-right alerta">${{ number_format($totalPendiente, 2) }}</td>
                            </tr>
                            @endif
                            @if($mostrarTotalFinanciero)
                            <tr class="total-final">
                                <td><strong>TOTAL FINANCIERO</strong></td>
                                <td class="text-right {{ $totalFinanciero >= 0 ? 'ganancia-positiva' : 'ganancia-negativa' }}">
                                    <strong>${{ number_format($totalFinanciero, 2) }}</strong>
                                </td>
                            </tr>
                            @endif
                        </table>
                    </div>
                </td>
            </tr>
        </tbody>
    </table>
</body>
</html>
