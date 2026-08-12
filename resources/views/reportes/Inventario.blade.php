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
                <div class="reporte-titulo">REPORTE DE INVENTARIO</div>
                <div class="reporte-periodo">Fecha: {{ date('d/m/Y') }}</div>
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

    <!-- INVENTARIO -->
    @if($productos->isNotEmpty())
        <div class="seccion-titulo">INVENTARIO</div>
        <table>
            <thead>
                <tr>
                    <th>N°</th>
                    <th>Producto</th>
                    <th>Sección</th>
                    <th>Marca</th>
                    <th>Categoría</th>
                    <th class="text-center">Stock</th>
                    <th class="text-center">Stock Mín.</th>
                    <th class="text-right">P. Detalle</th>
                    <th class="text-right">P. Mayor</th>
                    <th>Perec.</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                @foreach($productos as $p)
                <tr>
                    <td class="text-center">{{ $p->nro }}</td>
                    <td>{{ $p->nombre }}</td>
                    <td>{{ $p->seccion }}</td>
                    <td>{{ $p->marca }}</td>
                    <td>{{ $p->categoria }}</td>
                    <td class="text-center {{ $p->stock_bajo ? 'negativo' : '' }}">
                        <strong>{{ $p->stock }}</strong>
                    </td>
                    <td class="text-center">{{ $p->stock_minimo }}</td>
                    <td class="text-right">${{ number_format($p->precio_detalle, 2) }}</td>
                    <td class="text-right">${{ number_format($p->precio_mayor, 2) }}</td>
                    <td class="text-center">{{ $p->perecedero === 'PERECEDERO' ? 'Sí' : 'No' }}</td>
                    <td>
                        <span class="{{ $p->estado === 'ACTIVO' ? 'positivo' : 'negativo' }}">
                            {{ $p->estado }}
                        </span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div style="text-align: center; padding: 30px; color: #888;">No se encontraron productos con los filtros seleccionados.</div>
    @endif

    <!-- RESUMEN -->
    <table class="resumen-wrapper">
        <tbody>
            <tr>
                <td>
                    <div class="resumen-contenido">
                        <table style="width: 60%; margin-left: auto;">
                            <tr>
                                <td><strong>Total de Productos</strong></td>
                                <td class="text-right">{{ $totalProductos }}</td>
                            </tr>
                            <tr>
                                <td><strong>Total de Unidades en Stock</strong></td>
                                <td class="text-right">{{ $totalStock }}</td>
                            </tr>
                            <tr>
                                <td><strong>Productos Bajo Mínimo</strong></td>
                                <td class="text-right {{ $productosBajos > 0 ? 'negativo' : 'positivo' }}">
                                    {{ $productosBajos }}
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
