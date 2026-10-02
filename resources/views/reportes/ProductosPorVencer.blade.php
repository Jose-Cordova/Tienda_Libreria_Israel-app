<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    @include('reportes.css.Pdf')

    {{-- ✅ Estilos específicos para el Reporte de Productos por Vencer --}}
    <style>
        .header-table {
            padding-bottom: 14px;
            margin-bottom: 26px;
        }
        .empresa {
            font-size: 20px;
        }
        .reporte-titulo {
            font-size: 17px;
            letter-spacing: 1.2px;
        }
        .reporte-periodo {
            font-size: 11px;
            margin-top: 4px;
        }
        .seccion-titulo {
            font-size: 13px;
            padding: 8px 12px;
            margin-top: 24px;
            letter-spacing: 1px;
        }
        th {
            font-size: 11px;
            padding: 7px 9px;
        }
        td {
            font-size: 11px;
            padding: 7px 9px;
        }
    </style>
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
                <div class="reporte-titulo">PRODUCTOS PRÓXIMOS A VENCER</div>
                <div class="reporte-periodo">Fecha: {{ date('d/m/Y') }}</div>
            </td>
        </tr>
    </table>

    @if($productosAgrupados->isNotEmpty())
        <div class="seccion-titulo">PRODUCTOS PRÓXIMOS A VENCER (15 DÍAS)</div>

        @foreach($productosAgrupados as $index => $p)
            {{-- Tabla principal del producto --}}
            <table style="margin-bottom: 10px;">
                <thead>
                    <tr>
                        <th class="text-center">N°</th>
                        <th>Producto</th>
                        <th>Sección</th>
                        <th>Marca</th>
                        <th>Categoría</th>
                        <th class="text-center">Stock</th>
                        <th class="text-center">Lotes por Vencer</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="text-center">{{ $index + 1 }}</td>
                        <td>{{ $p['producto'] }}</td>
                        <td>{{ $p['seccion'] }}</td>
                        <td>{{ $p['marca'] }}</td>
                        <td>{{ $p['categoria'] }}</td>
                        <td class="text-center"><strong>{{ $p['stock'] }}</strong></td>
                        <td class="text-center alerta"><strong>{{ $p['total_lotes'] }}</strong></td>
                    </tr>
                </tbody>
            </table>

            {{-- Sub-tabla de lotes unificados --}}
            <table style="margin-top: -10px; margin-bottom: 15px; width: 95%; margin-left: 5%;">
                <thead>
                    <tr>
                        <th>Lote</th>
                        <th class="text-center">Vence</th>
                        <th class="text-center">Días Restantes</th>
                        <th class="text-center">Cantidad</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($p['lotes'] as $lote)
                    <tr>
                        <td>{{ $lote->codigo_lote }}</td>
                        <td class="text-center">{{ \Carbon\Carbon::parse($lote->fecha_vencimiento)->format('d/m/Y') }}</td>
                        <td class="text-center">
                            <span class="{{ $lote->dias_restantes <= 5 ? 'negativo' : 'alerta' }}">
                                {{ $lote->dias_restantes }} días
                            </span>
                        </td>
                        <td class="text-center"><strong>{{ $lote->cantidad_actual }}</strong></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endforeach
    @else
        <div style="text-align: center; padding: 40px; color: #888;">
            No hay productos próximos a vencer en los siguientes 15 días.
        </div>
    @endif

    <!-- RESUMEN -->
    <table class="resumen-wrapper">
        <tbody>
            <tr>
                <td>
                    <div class="resumen-contenido">
                        <table style="width: 60%; margin-left: auto;">
                            <tr>
                                <td><strong>Total de Productos por Vencer</strong></td>
                                <td class="text-right">{{ $totalProductos }}</td>
                            </tr>
                            <tr>
                                <td><strong>Total de Lotes por Vencer</strong></td>
                                <td class="text-right">{{ $totalLotes }}</td>
                            </tr>
                            <tr class="total-final">
                                <td><strong>TOTAL DE UNIDADES POR VENCER</strong></td>
                                <td class="text-right alerta">
                                    <strong>{{ $totalUnidades }}</strong>
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
