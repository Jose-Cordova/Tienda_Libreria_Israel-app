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


    {{-- RESUMEN DEL PERIODO --}}
    <div class="seccion-titulo">RESUMEN DEL PERIODO</div>
    <table class="tabla-resumen">
        <tr>
            <td class="resumen-label">Dinero recibido en caja</td>
            <td class="resumen-valor positivo">${{ number_format($totalCaja, 2) }}</td>
        </tr>
        <tr>
            <td class="resumen-label">Pendiente por cobrar (crédito)</td>
            <td class="resumen-valor alerta">${{ number_format($totalDeudas, 2) }}</td>
        </tr>
        <tr>
            <td class="resumen-label">Total gastado en compras</td>
            <td class="resumen-valor negativo">${{ number_format($totalCompras, 2) }}</td>
        </tr>
        <tr class="resumen-fila-total">
            <td class="resumen-label"><strong>Ganancia real del periodo</strong></td>
            <td class="resumen-valor {{ $ganancia >= 0 ? 'positivo' : 'negativo' }}">
                <strong>${{ number_format(abs($ganancia), 2) }}</strong>
            </td>
        </tr>
    </table>

    {{-- NOTIFICACIÓN DE ESTADO (GANANCIA/PÉRDIDA) --}}
    <div class="{{ $ganancia >= 0 ? 'indicador-positivo' : 'indicador-negativo' }}" style="margin-top: 10px; padding: 8px; border-radius: 5px;">
        @if ($ganancia >= 0)
            <strong>Ganancia real:</strong> ${{ number_format($ganancia, 2) }}
            — <strong>Deudas pendientes por cobrar:</strong> ${{ number_format($totalDeudas, 2) }}
        @else
            <strong>Pérdida de:</strong> ${{ number_format(abs($ganancia), 2) }}
            — <strong>Deudas pendientes por cobrar:</strong> ${{ number_format($totalDeudas, 2) }}
        @endif
    </div>

    <br>

    {{-- VENTAS --}}
    <div class="seccion-titulo">VENTAS ({{ $totalRegistrosV }} registros)</div>
    <table>
        <thead>
            <tr>
                <th width="20px"></th>
                <th>Correlativo</th>
                <th>Fecha</th>
                <th>Cliente</th>
                <th>Tipo</th>
                <th>Método Pago</th>
                <th>Estado</th>
                <th style="text-align:right;">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($ventas as $venta)
                <tr>
                    <td>{{ $loop->iteration }}</td> {{-- Contador automático --}}
                    <td>{{ $venta['correlativo'] }}</td>
                    <td>{{ $venta['fecha'] }}</td>
                    <td>{{ $venta['cliente'] }}</td>
                    <td>{{ ucfirst(strtolower($venta['tipo_cliente'])) }}</td>
                    <td>{{ $venta['metodo_pago'] }}</td>
                    <td>{{ ucfirst(strtolower($venta['estado'])) }}</td>
                    <td style="text-align:right;">${{ number_format($venta['total'], 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" style="text-align:center; color:#999; padding:10px;">
                        No hay ventas en el periodo seleccionado.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="resumen">
        <strong>En caja:</strong> ${{ number_format($totalCaja, 2) }}
        &nbsp;|&nbsp;
        <strong>Pendiente cobrar:</strong> ${{ number_format($totalDeudas, 2) }}
    </div>


    {{-- COMPRAS --}}
    <div class="seccion-titulo">COMPRAS A PROVEEDORES ({{ $totalRegistrosC }} registros)</div>
    <table>
        <thead>
            <tr>
                <th width="20px"></th>
                <th>Proveedor</th>
                <th>Teléfono</th>
                <th>N° Factura</th>
                <th>Fecha</th>
                <th style="text-align:right;">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($compras as $compra)
                <tr>
                    <td>{{ $loop->iteration }}</td> {{-- Contador automático --}}
                    <td>{{ $compra['proveedor'] }}</td>
                    <td>{{ $compra['telefono'] }}</td>
                    <td>{{ $compra['numero_factura'] }}</td>
                    <td>{{ $compra['fecha'] }}</td>
                    <td style="text-align:right;">${{ number_format($compra['total'], 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="text-align:center; color:#999; padding:10px;">
                        No hay compras en el periodo seleccionado.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="resumen">
        <strong>Total gastado en compras:</strong> ${{ number_format($totalCompras, 2) }}
    </div>


    {{-- PAGINACION --}}
    <script type="text/php">
        if ( isset($pdf) ) {
            $font = $fontMetrics->get_font("DejaVu Sans", "normal");
            $pdf->page_text(500, 820, "Página {PAGE_NUM} de {PAGE_COUNT}", $font, 9);
        }
    </script>

</body>
</html>
