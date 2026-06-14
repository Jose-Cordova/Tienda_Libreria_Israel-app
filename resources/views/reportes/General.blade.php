<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte General</title>
    <style>
        @include('reportes.css.Pdf')
    </style>
</head>
<body>

    {{-- ENCABEZADO --}}
    <table class="header">
        <tr>
            <td width="100%">
                <div class="empresa">Tienda &amp; Libreria Israel</div>
                <div class="titulo">REPORTE GENERAL</div>
                <div class="subtitulo">Del {{ $fechaInicio }} al {{ $fechaFin }}</div>
            </td>
        </tr>
    </table>


    {{-- VENTAS                                 --}}

    <div class="seccion-titulo">VENTAS ({{ $totalRegistrosV }} registros)</div>

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
                    <td>{{ $venta['correlativo'] }}</td>
                    <td>{{ $venta['fecha'] }}</td>
                    <td>{{ $venta['cliente'] }}</td>
                    <td>{{ ucfirst(strtolower($venta['tipo_cliente'])) }}</td>
                    <td>{{ $venta['metodo_pago'] }}</td>
                    <td>{{ ucfirst(strtolower($venta['estado'])) }}</td>
                    <td style="text-align:center;">{{ $venta['articulos'] }}</td>
                    <td style="text-align:right;">${{ number_format($venta['total'], 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" style="text-align:center; color:#999; padding:10px;">
                        No hay ventas en el periodo seleccionado.
                    </td>
                </tr>
            @endforelse
        </tbody>

        {{-- TOTALES DENTRO DEL CUADRO --}}
        <tfoot>
            <tr class="total-separador">
                <td colspan="9"></td>
            </tr>
            <tr class="total-fila">
                <td colspan="7">Ventas en efectivo</td>
                <td colspan="2" style="text-align:right;">${{ number_format($totalEfectivo, 2) }}</td>
            </tr>
            <tr class="total-fila">
                <td colspan="7">Ventas en transferencia</td>
                <td colspan="2" style="text-align:right;">${{ number_format($totalTransferencia, 2) }}</td>
            </tr>
            <tr class="total-fila credito-fila">
                <td colspan="7">Pendiente por cobrar (credito)</td>
                <td colspan="2" style="text-align:right;">${{ number_format($totalDeudas, 2) }}</td>
            </tr>
            <tr class="total-final">
                <td colspan="7"><strong>Total ventas cobradas</strong></td>
                <td colspan="2" style="text-align:right;"><strong>${{ number_format($totalCaja, 2) }}</strong></td>
            </tr>
        </tfoot>
    </table>



    {{-- COMPRAS                                --}}

    <div class="seccion-titulo">COMPRAS A PROVEEDORES ({{ $totalRegistrosC }} registros)</div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Proveedor</th>
                <th>Telefono</th>
                <th>N° Factura</th>
                <th>Fecha</th>
                <th style="text-align:center;">Productos</th>
                <th style="text-align:right;">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($compras as $i => $compra)
                <tr>
                    <td style="text-align:center;">{{ $i + 1 }}</td>
                    <td>{{ $compra['proveedor'] }}</td>
                    <td>{{ $compra['telefono'] }}</td>
                    <td>{{ $compra['numero_factura'] }}</td>
                    <td>{{ $compra['fecha'] }}</td>
                    <td style="text-align:center;">{{ $compra['productos'] }}</td>
                    <td style="text-align:right;">${{ number_format($compra['total'], 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="text-align:center; color:#999; padding:10px;">
                        No hay compras en el periodo seleccionado.
                    </td>
                </tr>
            @endforelse
        </tbody>

        {{-- TOTAL DENTRO DEL CUADRO --}}
        <tfoot>
            <tr class="total-separador">
                <td colspan="7"></td>
            </tr>
            <tr class="total-final">
                <td colspan="5"><strong>Total compras del periodo</strong></td>
                <td colspan="2" style="text-align:right;"><strong>${{ number_format($totalCompras, 2) }}</strong></td>
            </tr>
        </tfoot>
    </table>


    {{-- PAGINACION --}}
    <script type="text/php">
        if ( isset($pdf) ) {
            $font = $fontMetrics->get_font("DejaVu Sans", "normal");
            $pdf->page_text(500, 820, "Pagina {PAGE_NUM} de {PAGE_COUNT}", $font, 9);
        }
    </script>

</body>
</html>
