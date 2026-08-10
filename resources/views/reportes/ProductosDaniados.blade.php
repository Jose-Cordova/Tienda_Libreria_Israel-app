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
                <div class="reporte-titulo">REPORTE DE PRODUCTOS DAÑADOS</div>
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

    <!-- PRODUCTOS DAÑADOS -->
    @if($daniados->isNotEmpty())
        <div class="seccion-titulo">PRODUCTOS DAÑADOS</div>
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
                    <td>{{ $pd->origen }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div style="text-align: center; padding: 30px; color: #888;">No se encontraron productos dañados en el período.</div>
    @endif

    <!-- RESUMEN -->
    <table class="resumen-wrapper">
        <tbody>
            <tr>
                <td>
                    <div class="resumen-contenido">
                        <table style="width: 60%; margin-left: auto;">
                            <tr>
                                <td><strong>Registros de Daños</strong></td>
                                <td class="text-right">{{ $cantidadDaniados }}</td>
                            </tr>
                            <tr>
                                <td><strong>Total Unidades Dañadas</strong></td>
                                <td class="text-right">{{ $totalCantidad }}</td>
                            </tr>
                            <tr class="total-final">
                                <td><strong>TOTAL PÉRDIDA</strong></td>
                                <td class="text-right negativo">
                                    <strong>${{ number_format($totalPerdida, 2) }}</strong>
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
