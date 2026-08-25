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
        <td style="vertical-align: middle;">
            <div class="empresa">{{ $config->nombre_tienda }}</div>
            <div class="empresa-detalle">
                Tel: {{ $config->telefono }} | Email: {{ $config->email }}
            </div>
        </td>
        <td class="reporte-info" style="vertical-align: middle;">
            <div class="reporte-titulo">REPORTE DE <br>DEVOLUCIONES DE VENTAS</div>
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

    {{-- Si estado es DEVUELTA o ANULADA: una sola tabla --}}
    @if($estado === 'DEVUELTA' || $estado === 'ANULADA')
    @if($todos->isNotEmpty())
    <div class="seccion-titulo">{{ $estado }}</div>
    <table>
        <thead>
            <tr>
                <th class="text-center">N°</th>
                <th>Venta</th>
                <th>Producto</th>
                <th class="text-center">Cant.</th>
                <th class="text-right">P. Unit.</th>
                <th class="text-right">Total</th>
                <th>Lote</th>
                <th>Condición</th>
            </tr>
        </thead>
        <tbody>
            @foreach($todos as $d)
            <tr>
                <td class="text-center">{{ $d->nro }}</td>
                <td>{{ $d->venta_correlativo }}</td>
                <td>{{ $d->producto }}</td>
                <td class="text-center">{{ $d->cantidad }}</td>
                <td class="text-right">${{ number_format($d->precio_unitario, 2) }}</td>
                <td class="text-right">${{ number_format($d->subtotal, 2) }}</td>
                <td>{{ $d->lote ?? '-' }}</td>
                <td>{{ $d->condicion }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @else
    <p style="text-align:center; color:#888;">No se encontraron devoluciones.</p>
    @endif
    @else
    {{-- Separación por condición --}}
    @if($perfectos->isNotEmpty())
    <div class="seccion-titulo">PERFECTO</div>
    <table>
        <thead>
            <tr>
                <th class="text-center">N°</th>
                <th>Venta</th>
                <th>Producto</th>
                <th class="text-center">Cant.</th>
                <th class="text-right">P. Unit.</th>
                <th class="text-right">Total</th>
                <th>Lote</th>
            </tr>
        </thead>
        <tbody>
            @foreach($perfectos as $d)
            <tr>
                <td class="text-center">{{ $d->nro }}</td>
                <td>{{ $d->venta_correlativo }}</td>
                <td>{{ $d->producto }}</td>
                <td class="text-center">{{ $d->cantidad }}</td>
                <td class="text-right">${{ number_format($d->precio_unitario, 2) }}</td>
                <td class="text-right">${{ number_format($d->subtotal, 2) }}</td>
                <td>{{ $d->lote ?? '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    @if($danados->isNotEmpty())
    <div class="seccion-titulo">DAÑADO</div>
    <table>
        <thead>
            <tr>
                <th class="text-center">N°</th>
                <th>Venta</th>
                <th>Producto</th>
                <th class="text-center">Cant.</th>
                <th class="text-right">P. Unit.</th>
                <th class="text-right">Total</th>
                <th>Lote</th>
            </tr>
        </thead>
        <tbody>
            @foreach($danados as $d)
            <tr>
                <td class="text-center">{{ $d->nro }}</td>
                <td>{{ $d->venta_correlativo }}</td>
                <td>{{ $d->producto }}</td>
                <td class="text-center">{{ $d->cantidad }}</td>
                <td class="text-right">${{ number_format($d->precio_unitario, 2) }}</td>
                <td class="text-right">${{ number_format($d->subtotal, 2) }}</td>
                <td>{{ $d->lote ?? '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    @if($perfectos->isEmpty() && $danados->isEmpty())
    <p style="text-align:center; color:#888;">No se encontraron devoluciones.</p>
    @endif
    @endif

    <!-- RESUMEN -->
    <table class="resumen-wrapper">
    <tbody>
        <tr>
            <td>
                <div class="resumen-contenido">
                    <table style="width: 60%; margin-left: auto;">
                        <tr>
                            <td><strong>Total de Registros</strong></td>
                            <td class="text-right">{{ $totalRegistros }}</td>
                        </tr>

                        {{-- Mostrar desglose solo si NO es filtro de DEVUELTA o ANULADA --}}
                        @if($estado !== 'DEVUELTA' && $estado !== 'ANULADA')
                            {{-- Perfectos: se muestran si no se filtro por DANIADO --}}
                            @if($estado !== 'DANIADO')
                                <tr>
                                    <td><strong>Cantidad Perfectos</strong></td>
                                    <td class="text-right ganancia-positiva">{{ $cantidadPerfectos }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Total Perfectos</strong></td>
                                    <td class="text-right ganancia-positiva">${{ number_format($totalPerfectos, 2) }}</td>
                                </tr>
                            @endif

                            {{-- Dañados: se muestran si no se filtro por PERFECTO --}}
                            @if($estado !== 'PERFECTO')
                                <tr>
                                    <td><strong>Cantidad Dañados</strong></td>
                                    <td class="text-right">{{ $cantidadDanados }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Total Dañados</strong></td>
                                    <td class="text-right negativo">${{ number_format($totalDanados, 2) }}</td>
                                </tr>
                            @endif
                        @endif
                    </table>
                </div>
            </td>
        </tr>
    </tbody>
    </table>
    </div>
    </td>
    </tr>
    </tbody>
    </table>
</body>
</html>
