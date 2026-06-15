<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte Historial de Ventas</title>
    <style>
        @include('reportes.css.Historialpdf')
    </style>
</head>
<body>

    {{--  ENCABEZADO  --}}
    <table class="header">
        <tr>
            <td width="100%">
                <div class="empresa">Tienda &amp; Libreria Israel</div>
                <div class="titulo">REPORTE DE HISTORIAL DE VENTAS</div>
                <div class="subtitulo">Del {{ $fechaInicio }} al {{ $fechaFin }}</div>
                <div class="meta">Generado: {{ $generadoEn }}</div>
            </td>
        </tr>
    </table>


    {{-- DETALLE DE VENTAS --}}
    <div class="seccion-titulo">VENTAS ({{ $totalRegistros }} registros)</div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Correlativo</th>
                <th>Fecha</th>
                <th>Cliente</th>
                <th>Tipo</th>
                <th>Metodo Pago</th>
                <th>Estado</th>
                <th style="text-align:center;">Arts.</th>
                <th style="text-align:right;">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($ventas as $i => $venta)
                <tr>
                    <td style="text-align:center;">{{ $i + 1 }}</td>
                    <td><strong>{{ $venta['correlativo'] }}</strong></td>
                    <td>{{ $venta['fecha'] }} {{ $venta['hora'] }}</td>
                    <td>{{ $venta['cliente'] }}</td>
                    <td>{{ $venta['tipo_cliente'] }}</td>
                    <td>{{ $venta['metodo_pago'] }}</td>
                    <td>
                        @if ($venta['estado'] === 'PAGADA')
                            <span class="badge badge-pagada">Pagada</span>
                        @elseif ($venta['estado'] === 'CREDITO')
                            <span class="badge badge-credito">Crédito</span>
                        @else
                            <span class="badge badge-anulada">Anulada</span>
                        @endif
                    </td>
                    <td style="text-align:center;">{{ $venta['articulos'] }}</td>
                    <td style="text-align:right;">${{ number_format($venta['total'], 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" style="text-align:center; color:#999; padding:14px; font-style:italic;">
                        No hay ventas en el periodo seleccionado.
                    </td>
                </tr>
            @endforelse
        </tbody>

        {{--  RESUMEN, TOTALES  --}}
        <tfoot>
            <tr class="total-separador">
                <td colspan="9"></td>
            </tr>

            @foreach ($totalesPorMetodo as $metodo => $monto)
                <tr class="total-fila">
                    <td colspan="7">Ventas en {{ $metodo }}</td>
                    <td colspan="2" style="text-align:right;">${{ number_format($monto, 2) }}</td>
                </tr>
            @endforeach

            @if ($totalDeudas > 0)
                <tr class="total-fila credito-fila">
                    <td colspan="7">Pendiente por cobrar (crédito)</td>
                    <td colspan="2" style="text-align:right;">${{ number_format($totalDeudas, 2) }}</td>
                </tr>
            @endif

            @if ($totalAnuladas > 0)
                <tr class="total-fila anulada-fila">
                    <td colspan="7">Ventas anuladas</td>
                    <td colspan="2" style="text-align:right;">${{ number_format($totalAnuladas, 2) }}</td>
                </tr>
            @endif

            <tr class="total-final">
                <td colspan="7"><strong>Total ventas cobradas</strong></td>
                <td colspan="2" style="text-align:right;"><strong>${{ number_format($totalCobrado, 2) }}</strong></td>
            </tr>
        </tfoot>
    </table>


    {{--PAGINACIÓN --}}
    <script type="text/php">
        if ( isset($pdf) ) {
            $font = $fontMetrics->get_font("DejaVu Sans", "normal");
            $pdf->page_text(500, 820, "Pagina {PAGE_NUM} de {PAGE_COUNT}", $font, 9);
        }
    </script>

</body>
</html>
