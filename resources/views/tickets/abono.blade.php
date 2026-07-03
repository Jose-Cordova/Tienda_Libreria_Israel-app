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
            width: 200px;
        }
        .store-name { font-size: 14px; font-weight: bold; text-align: center; }
        .store-info { text-align: center; margin-bottom: 10px; }
        .separator { border-top: 1px dashed #000; margin: 5px 0; }
        .total { font-size: 16px; font-weight: bold; text-align: right; margin-top: 10px; }
    </style>
</head>
<body>
    <div class="store-name">{{ $config->nombre_tienda }}</div>
    <div class="store-info">
        Tel: {{ $config->telefono }}<br>
        Email: {{ $config->email }}
    </div>

    <div class="separator"></div>

    <div><strong>Comprobante de Abono</strong></div>
    <div>Crédito Nº: {{ $abono->credito_numero }}</div>
    <div>Abono Nº: {{ $abono->abono_numero }}</div>
    <div>Fecha: {{ $abono->fecha_abono }}</div>
    <div>Cliente: {{ $abono->cliente_nombre }}</div>
    <div>Método de pago: {{ $abono->metodo_pago }}</div>

    <div class="separator"></div>

    <div>Monto abonado: <strong>${{ number_format($abono->monto, 2) }}</strong></div>
    <div>Deuda: ${{ number_format($abono->monto_adeudado - ($abono->saldo_actual - $abono->monto), 2) }}</div>
    <div>Saldo pendiente: ${{ number_format($abono->monto_adeudado - $abono->saldo_actual, 2) }}</div>

    <div class="separator"></div>

    <div class="total">Total abonado: ${{ number_format($abono->monto, 2) }}</div>

    <div class="separator"></div>
    <div style="text-align:center; margin-top:10px;">¡Gracias por su pago!</div>
</body>
</html>
