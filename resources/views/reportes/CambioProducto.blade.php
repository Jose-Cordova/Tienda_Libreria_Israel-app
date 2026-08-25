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
                <div class="reporte-titulo">REPORTE DE CAMBIO DE PRODUCTO</div>
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

    @if($agrupados->isEmpty())
        <div style="text-align: center; padding: 30px; color: #888;">No se encontraron cambios de producto en el período.</div>
    @else
        @foreach($agrupados as $estado => $subgrupos)
            <div class="seccion-titulo">{{ $estado }}</div>

            @php
                $registros = collect();
                foreach ($subgrupos as $grupo) {
                    $registros = $registros->merge($grupo);
                }
                $registros = $registros->sortBy('nro');
            @endphp

            @if($estado === 'ACEPTADO')
                <table>
                    <thead>
                        <tr>
                            <th class="text-center">N°</th>
                            <th>Fecha</th>
                            <th>Producto</th>
                            <th>Producto Reemplazo</th>
                            <th class="text-center">Cant.</th>
                            <th class="text-right">Costo Unit.</th>
                            <th class="text-right">Total Pérdida</th>
                            <th>Lote</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($registros as $cp)
                        <tr>
                            <td class="text-center">{{ $cp->nro }}</td>
                            <td>{{ $cp->fecha }}</td>
                            <td>{{ $cp->producto }}</td>
                            <td>{{ $cp->producto_reemplazo ?? '-' }}</td>
                            <td class="text-center">{{ $cp->cantidad }}</td>
                            <td class="text-right">${{ number_format($cp->costo_unitario, 2) }}</td>
                            <td class="text-right">${{ number_format($cp->total_perdida, 2) }}</td>
                            <td>{{ $cp->lote ?? '-' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <table>
                    <thead>
                        <tr>
                            <th class="text-center">N°</th>
                            <th>Fecha</th>
                            <th>Producto</th>
                            <th class="text-center">Cant.</th>
                            <th class="text-right">Costo Unit.</th>
                            <th class="text-right">Total Pérdida</th>
                            <th>Lote</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($registros as $cp)
                        <tr>
                            <td class="text-center">{{ $cp->nro }}</td>
                            <td>{{ $cp->fecha }}</td>
                            <td>{{ $cp->producto }}</td>
                            <td class="text-center">{{ $cp->cantidad }}</td>
                            <td class="text-right">${{ number_format($cp->costo_unitario, 2) }}</td>
                            <td class="text-right">${{ number_format($cp->total_perdida, 2) }}</td>
                            <td>{{ $cp->lote ?? '-' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        @endforeach
    @endif

    <!-- RESUMEN (solo filas con valores > 0) -->
    <table class="resumen-wrapper">
        <tbody>
            <tr>
                <td>
                    <div class="resumen-contenido">
                        <table style="width: 60%; margin-left: auto;">
                            @if($totales['cantidadConReemplazo'] > 0)
                            <tr>
                                <td><strong>Cambio con producto diferente</strong></td>
                                <td class="text-right">{{ $totales['cantidadConReemplazo'] }}</td>
                            </tr>
                            @endif

                            @if($totales['cantidadAceptados'] > 0)
                            <tr>
                                <td><strong>Cambios aceptados</strong></td>
                                <td class="text-right">{{ $totales['cantidadAceptados'] }}</td>
                            </tr>
                            @endif

                            @if($totales['cantidadRechazados'] > 0)
                            <tr>
                                <td><strong>Cambios rechazados</strong></td>
                                <td class="text-right">{{ $totales['cantidadRechazados'] }}</td>
                            </tr>
                            @endif

                            @if($totales['cantidadAnulados'] > 0)
                            <tr>
                                <td><strong>Cambios anulados</strong></td>
                                <td class="text-right">{{ $totales['cantidadAnulados'] }}</td>
                            </tr>
                            @endif

                            @if($totales['totalPendienteCantidad'] > 0)
                            <tr class="alerta">
                                <td><strong>Pendientes (cantidad)</strong></td>
                                <td class="text-right">{{ $totales['totalPendienteCantidad'] }}</td>
                            </tr>
                            <tr class="alerta">
                                <td><strong>Pendientes (costo)</strong></td>
                                <td class="text-right">${{ number_format($totales['totalPendienteCosto'], 2) }}</td>
                            </tr>
                            @endif

                            @if($totales['totalPerdida'] > 0)
                            <tr class="total-final">
                                <td><strong>TOTAL PÉRDIDA (RECHAZADOS)</strong></td>
                                <td class="text-right negativo">
                                    <strong>${{ number_format($totales['totalPerdida'], 2) }}</strong>
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
