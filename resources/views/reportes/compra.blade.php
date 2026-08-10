<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Compras</title>
    <style>
        @include('reportes.css.Compraspdf')
    </style>
</head>
<body>

    {{-- ── ENCABEZADO ── --}}
    <table class="header">
        <tr>
            <td width="100%">
                <div class="empresa">Tienda &amp; Libreria Israel</div>
                <div class="titulo">REPORTE DE COMPRAS A PROVEEDORES</div>
                <div class="subtitulo">Del {{ $fechaInicio }} al {{ $fechaFin }}</div>
                <div class="meta">Generado: {{ $generadoEn }}</div>
            </td>
        </tr>
    </table>

    {{-- ── DETALLE ── --}}
    <div class="seccion-titulo">COMPRAS A PROVEEDORES ({{ $totalRegistros }} registros)</div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>N° Factura</th>
                <th>Proveedor</th>
                <th>Teléfono</th>
                <th>Fecha</th>
                <th>Estado</th>
                <th style="text-align:center;">Prods.</th>
                <th style="text-align:right;">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($compras as $i => $compra)
                <tr>
                    <td style="text-align:center;">{{ $i + 1 }}</td>
                    <td><strong>{{ $compra['numero_factura'] }}</strong></td>
                    <td>{{ $compra['proveedor'] }}</td>
                    <td>{{ $compra['telefono'] }}</td>
                    <td>{{ $compra['fecha'] }}</td>
                    <td>
                        @if ($compra['estado'] === 'REGISTRADA')
                            <span style="background:#e6f4ea;color:#2d7a3a;padding:2px 6px;border-radius:3px;font-size:9px;font-weight:bold;">Registrada</span>
                        @else
                            <span style="background:#fce8e8;color:#b71c1c;padding:2px 6px;border-radius:3px;font-size:9px;font-weight:bold;">Anulada</span>
                        @endif
                    </td>
                    <td style="text-align:center;">{{ $compra['productos'] }}</td>
                    <td style="text-align:right;">${{ number_format($compra['total'], 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" style="text-align:center;color:#999;padding:14px;font-style:italic;">
                        No hay compras en el periodo seleccionado.
                    </td>
                </tr>
            @endforelse
        </tbody>

        {{-- ── RESUMEN ── --}}
        <tfoot>
            <tr class="total-separador"><td colspan="8"></td></tr>
            <tr class="total-fila">
                <td colspan="6">Total compras registradas</td>
                <td colspan="2" style="text-align:right;">${{ number_format($totalRegistradas, 2) }}</td>
            </tr>
            @if ($totalAnuladas > 0)
            <tr class="total-fila anulada-fila">
                <td colspan="6">Total compras anuladas</td>
                <td colspan="2" style="text-align:right;">${{ number_format($totalAnuladas, 2) }}</td>
            </tr>
            @endif
            <tr class="total-final">
                <td colspan="6"><strong>Total invertido en compras</strong></td>
                <td colspan="2" style="text-align:right;"><strong>${{ number_format($totalGeneral, 2) }}</strong></td>
            </tr>
        </tfoot>
    </table>

    {{-- ── PAGINACIÓN ── --}}
    <script type="text/php">
        if ( isset($pdf) ) {
            $font = $fontMetrics->get_font("DejaVu Sans", "normal");
            $pdf->page_text(500, 820, "Pagina {PAGE_NUM} de {PAGE_COUNT}", $font, 9);
        }
    </script>

</body>
</html>
