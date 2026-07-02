<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            margin: 0;
            padding: 0;
            width: 200px; /* ligeramente menor al ancho del papel */
        }
        .store-name { font-size: 14px; font-weight: bold; text-align: center; }
        .store-info { text-align: center; margin-bottom: 10px; }
        .separator { border-top: 1px dashed #000; margin: 5px 0; }
        .producto { margin: 2px 0; }
        .producto span { display: inline-block; }
        .precio { float: right; }
        .total { font-size: 16px; font-weight: bold; text-align: right; margin-top: 10px; }
        .credito { margin-top: 10px; border-top: 1px dashed #000; padding-top: 5px; }
    </style>
</head>
<body>
    <div class="store-name">{{ $config->nombre_tienda }}</div>
    <div class="store-info">
        Tel: {{ $config->telefono }}<br>
        Email: {{ $config->email }}
    </div>

    <div class="separator"></div>

    <div>Correlativo: {{ $venta->correlativo }}</div>
    <div>Fecha: {{ $venta->fecha }}</div>
    <div>Vendedor: {{ $venta->vendedor }}</div>
    <div>Método: {{ $venta->metodo_pago }}</div>
    <div>Tipo: {{ $venta->tipo_cliente }}</div>

    <div class="separator"></div>

    @foreach ($detalles as $detalle)
        <div class="producto">
            <span>{{ $detalle->producto }}</span>
            <span style="float:right;">x{{ $detalle->cantidad }}</span>
        </div>
        <div class="producto">
            <span>${{ number_format($detalle->precio_unitario, 2) }}</span>
            <span class="precio">${{ number_format($detalle->subtotal, 2) }}</span>
        </div>
    @endforeach

    <div class="separator"></div>

    <div class="total">Total: ${{ number_format($venta->total, 2) }}</div>

    @if ($credito)
        <div class="credito">
            <strong>Crédito a:</strong> {{ $credito->cliente }}<br>
            <strong>Monto adeudado:</strong> ${{ number_format($credito->monto_adeudado, 2) }}
        </div>
    @endif

    <div class="separator"></div>
    <div style="text-align:center; margin-top:10px;">¡Gracias por su compra!</div>
</body>
</html>
