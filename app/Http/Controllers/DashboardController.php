<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    // Obtenemos todos los datos para el dashboard
    public function index(Request $request){
        try{
            $ventasHoy = $this->getVentasDelDia();
            $creditosPendientes = $this->getCreditosPendientes();
            $periodo = $request->get('periodo', 'day');
            $tendencia = $this->getTendenciaVentas($periodo);
            $gananciasPerdidas = $this->getGananciasVsPerdidas();
            $topProductos = $this->getTopProductos();
            $metodosPago = $this->getMetodosPago();

            //Alertas
            $stockBajo = $this->getStockBajo();
            $vencimientos = $this->getVencimientos();
            $proximasVisitas = $this->getProximasVisitas();

            return response()->json([
                'ventas_hoy' => $ventasHoy,
                'creditos_pendientes' => $creditosPendientes,
                'tendencia' => $tendencia,
                'ganancias_perdidas' => $gananciasPerdidas,
                'top_productos' => $topProductos,
                'metodos_pago' => $metodosPago,
                'stock_bajo' => $stockBajo,
                'vencimientos' => $vencimientos,
                'proximas_visitas' => $proximasVisitas
            ], 200);

        } catch(\Exception $e){
            Log::error('Error en DashboardController: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al obtener los datos.',
                'errors' => $e->getMessage()
            ], 500);
        }
    }

    //Ventas del dia
    public function getVentasDelDia()
    {
        $total = DB::table('ventas')
            ->whereDate('fecha', now()->toDateString())
            ->where('estado', 'PAGADA')
            ->sum('total');
        return round($total, 2);
    }

    //Total de creditos pendientes
    public function getCreditosPendientes()
    {
        $total = DB::table('creditos')
            ->where('saldo', '>', 0)
            ->sum('saldo');

        return round($total, 2);
    }

    //Alertas de Stock Bajo
    public function getStockBajo()
    {
        return DB::table('productos')
            ->where('estado', 'ACTIVO')
            ->whereColumn('stock', '<=', 'stock_minimo')
            ->select('id', 'nombre', 'stock', 'stock_minimo as minimo')
            ->get();
    }

    //Alertas de Vencimiento
    public function getVencimientos()
    {
        return DB::table('lotes')
            ->join('productos', 'lotes.producto_id', '=', 'productos.id')
            ->where('lotes.estado', 'ACTIVO')
            ->where('lotes.cantidad_actual', '>', 0)
            ->where('lotes.fecha_vencimiento', '<=', now()->addDays(15))
            ->where('lotes.fecha_vencimiento', '>=', now())
            ->select('productos.nombre', 'lotes.codigo_lote as lote', 'lotes.fecha_vencimiento as fecha')
            ->orderBy('lotes.fecha_vencimiento', 'asc')
            ->get();
    }

    //Próximas visitas de proveedores
    public function getProximasVisitas()
    {
        return DB::table('cronograma_proveedores')
            ->join('proveedores', 'cronograma_proveedores.proveedor_id', '=', 'proveedores.id')
            ->where('cronograma_proveedores.fecha', '>=', now()->toDateString())
            ->where('cronograma_proveedores.fecha', '<=', now()->addDays(2)->toDateString())
            ->select('proveedores.nombre', 'cronograma_proveedores.fecha')
            ->orderBy('cronograma_proveedores.fecha', 'asc')
            ->get()
            ->map(function($v) {
                $v->dias = now()->diffInDays($v->fecha);
                return $v;
            });
    }

    public function getTendenciaVentas($periodo = 'day')
    {
        switch($periodo){
            case 'day':
                return $this->tendenciaGlobal(7, 'day', 'D');
            case 'month':
                return $this->tendenciaGlobal(6, 'month', 'M');
            case 'year':
                return $this->tendenciaGlobal(3, 'year', 'Y');
            default:
                return $this->tendenciaGlobal(7, 'day', 'D');
        }
    }

    //Tendencia de ventas
    public function tendenciaGlobal($cantidad, $tipo, $formato)
    {
        $labels = [];
        $data = [];

        //Calcular fecha de inicio
        $fechaInicio = match($tipo) {
            'day' => now()->subDays($cantidad - 1)->startOfDay(),
            'month' => now()->subMonths($cantidad - 1)->startOfMonth(),
            'year' => now()->subYears($cantidad - 1)->startOfYear()
        };

        $query = DB::table('ventas')
            ->where('fecha', '>=', $fechaInicio)
            ->where('estado', 'PAGADA');

        if($tipo === 'day'){
            $resultados = $query->select(DB::raw('CAST(fecha AS DATE) as p'), DB::raw('SUM(total) as t'))
                ->groupBy('p')
                ->pluck('t', 'p');
        }elseif($tipo === 'month'){
            $resultados = $query->select(DB::raw("TO_CHAR(fecha, 'YYYY-MM') as p"), DB::raw('SUM(total) as t'))
                ->groupBy('p')
                ->pluck('t', 'p');
        }else{
            $resultados = $query->select(DB::raw("TO_CHAR(fecha, 'YYYY') as p"), DB::raw('SUM(total) as t'))
                ->groupBy('p')
                ->pluck('t', 'p');
        }

        //Rellenar huecos en PHP
        for($i = $cantidad - 1; $i >= 0; $i--){
            $fecha = match($tipo){
                'day' => now()->subDays($i),
                'month' => now()->subMonths($i),
                'year' => now()->subYears($i)
            };
            $key = match($tipo){
                'day' => $fecha->toDateString(),
                'month' => $fecha->format('Y-m'),
                'year' => $fecha->format('Y')
            };

            $labels[] = ($tipo === 'month') ? ucfirst($fecha->locale('es')->isoFormat('MMM')) : $fecha->format($formato);
            $data[] = round($resultados[$key] ?? 0, 2);
        }

        return [
            'labels' => $labels,
            'data' => $data
        ];
    }

    //Ganancias vs perdidas
    public function getGananciasVsPerdidas()
    {
        $labels = [];
        $ganancias = [];
        $perdidas = [];
        $fechaInicio = now()->subMonths(11)->startOfMonth();

        //Obtener ventas (ganancias) agrupadas por mes
        $ventas = DB::table('ventas')
            ->select(DB::raw("TO_CHAR(fecha, 'YYYY-MM') as mes"), DB::raw('SUM(total) as total'))
            ->where('fecha', '>=', $fechaInicio)
            ->where('estado', 'PAGADA')
            ->groupBy('mes')
            ->pluck('total', 'mes');

        //Obtener mermas (pérdidas) agrupadas por mes
        $daños = DB::table('productos_daniados')
            ->select(DB::raw("TO_CHAR(fecha, 'YYYY-MM') as mes"), DB::raw('SUM(total_perdida) as total'))
            ->where('fecha', '>=', $fechaInicio)
            ->groupBy('mes')
            ->pluck('total', 'mes');

        for($i = 11; $i >= 0; $i--){
            $fecha = now()->subMonths($i);
            $key = $fecha->format('Y-m');

            $labels[] = ucfirst($fecha->locale('es')->isoFormat('MMM'));
            $ganancias[] = round($ventas[$key] ?? 0, 2);
            $perdidas[] = round($daños[$key] ?? 0, 2);
        }

        return [
            'labels' => $labels,
            'ganancias' => $ganancias,
            'perdidas' => $perdidas
        ];
    }

    //Top 5 productos mas vendidos
    public function getTopProductos()
    {
        return DB::table('detalle_ventas')
            ->join('productos', 'detalle_ventas.producto_id', '=', 'productos.id')
            ->select('productos.id', 'productos.nombre', DB::raw('SUM(detalle_ventas.cantidad) as total_vendido'))
            ->groupBy('productos.id', 'productos.nombre')
            ->orderBy('total_vendido', 'desc')
            ->limit(5)
            ->get()
            ->map(fn($item) => [
                'id' => $item->id,
                'nombre' => $item->nombre,
                'total_vendido' => (int)$item->total_vendido
            ])
            ->toArray();
    }

    //Metodos de pagos mas utilizados (Dinámico)
    public function getMetodosPago()
    {
        $mes = now()->month;
        $anio = now()->year;

        return DB::table('ventas')
            ->join('metodos_pagos', 'ventas.metodo_pago_id', '=', 'metodos_pagos.id')
            ->select('metodos_pagos.nombre', DB::raw('SUM(ventas.total) as total'))
            ->whereYear('ventas.fecha', $anio)
            ->whereMonth('ventas.fecha', $mes)
            ->where('ventas.estado', 'PAGADA')
            ->groupBy('metodos_pagos.nombre')
            ->pluck('total', 'nombre')
            ->map(fn($total) => round($total, 2));
    }
}
