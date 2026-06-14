<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Créditos</title>
    <style>
        @include('reportes.css.Creditopdf')
    </style>
</head>
<body>

    {{-- ── ENCABEZADO ── --}}
    <table class="header">
        <tr>
            <td width="100%">
                <div class="empresa">Tienda &amp; Libreria Israel</div>
                <div class="titulo">REPORTE DE CRÉDITOS / FIADOS</div>
                <div class="subtitulo">Clientes con saldo pendiente</div>
                <div class="meta">Generado: {{ $fecha }}</div>
            </td>
        </tr>
    </table>

    {{-- ── DETALLE ── --}}
    <div class="seccion-titulo">CLIENTES CON SALDO PENDIENTE ({{ $totalRegistros }} registros)</div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Cliente</th>
                <th>Teléfono</th>
                <th style="text-align:right;">Total Deuda</th>
                <th style="text-align:right;">Total Abonado</th>
                <th style="text-align:right;">Saldo Pendiente</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($deudores as $i => $deudor)
                <tr>
                    <td style="text-align:center;">{{ $i + 1 }}</td>
                    <td><strong>{{ $deudor['nombre'] }}</strong></td>
                    <td>{{ $deudor['telefono'] }}</td>
                    <td style="text-align:right;">${{ number_format($deudor['total_deuda'], 2) }}</td>
                    <td style="text-align:right;" class="positivo">${{ number_format($deudor['total_abonado'], 2) }}</td>
                    <td style="text-align:right;" class="alerta"><strong>${{ number_format($deudor['saldo_pendiente'], 2) }}</strong></td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="text-align:center;color:#999;padding:14px;font-style:italic;">
                        No hay clientes con saldo pendiente.
                    </td>
                </tr>
            @endforelse
        </tbody>

        {{-- ── RESUMEN ── --}}
        <tfoot>
            <tr class="total-separador"><td colspan="6"></td></tr>
            <tr class="total-final">
                <td colspan="5"><strong>Total pendiente por cobrar</strong></td>
                <td style="text-align:right;"><strong>${{ number_format($totalPendiente, 2) }}</strong></td>
            </tr>
        </tfoot>
    </table>

    {{-- ── PAGINACIÓN ── --}}
    <script type="text/php">
        if ( isset($pdf) ) {
            $font = $fontMetrics->get_font("DejaVu Sans", "normal");
            $pdf->page_text(270, 830, "Pagina {PAGE_NUM} de {PAGE_COUNT}", $font, 9);
        }
    </script>

</body>
</html>
