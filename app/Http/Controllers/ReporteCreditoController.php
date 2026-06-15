<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class ReporteCreditoController extends Controller
{
    public function creditosDatos(Request $request)
    {
        $deudores = $this->obtenerCreditos($request);
        return response()->json(['deudores' => $deudores->values()]);
    }

    public function reporteCreditos(Request $request)
    {
        $fecha    = Carbon::now()->format('d/m/Y H:i');
        $deudores = $this->obtenerCreditos($request);

        $totalPendiente = $deudores->sum('saldo_pendiente');
        $totalRegistros = $deudores->count();

        return Pdf::loadView('reportes.credito', compact(
            'deudores', 'totalPendiente', 'totalRegistros', 'fecha'
        ))
        ->setPaper('a4', 'portrait')
        ->stream('reportes_creditos.pdf');
    }

    private function obtenerCreditos(Request $request)
    {
        $fechaInicio = $request->fecha_inicio;
        $fechaFin    = $request->fecha_fin;

        $query = DB::table('clientes_creditos as cc')
            ->select(
                'cc.id',
                'cc.nombre',
                'cc.telefono',
                // se agrega un sub consulta y transforma  cada venta para darle formato al reporte
                DB::raw("(SELECT COALESCE(SUM(v.total), 0)
                    FROM ventas v
                    JOIN creditos c ON v.id = c.venta_id
                    WHERE c.cliente_credito_id = cc.id" .
                    // Si se han proporcionado fechas, se filtra por el rango
                    ($fechaInicio && $fechaFin ? " AND c.created_at BETWEEN '$fechaInicio 00:00:00' AND '$fechaFin 23:59:59'" : "") .
                    ") as total_deuda"),
                    // Se agrega una subconsulta para calcular el total abonado por cada cliente
                DB::raw("(SELECT COALESCE(SUM(a.monto), 0)
                    FROM abonos a
                    JOIN creditos c ON a.credito_id = c.id
                    WHERE c.cliente_credito_id = cc.id" .
                    // Si se han proporcionado fechas, se filtra por el rango
                    ($fechaInicio && $fechaFin ? " AND a.created_at BETWEEN '$fechaInicio 00:00:00' AND '$fechaFin 23:59:59'" : "") .
                    ") as total_abonado")
            );

            //Ejecuta la consulta y recorre cada cliente para calcular su saldo actual
        return $query->get()->map(function ($c) use ($request) {
            // Convierte a decimales y resta
            $saldo = (float)$c->total_deuda - (float)$c->total_abonado;

            // Filtro preliminar por estado
            if ($request->estado === 'PENDIENTE' && $saldo <= 0) return null;
            if ($request->estado === 'PAGADO'    && $saldo > 0)  return null;

            return [
                'id'              => $c->id,
                'nombre'          => $c->nombre,
                'telefono'        => $c->telefono,
                'total_deuda'     => (float)$c->total_deuda,
                'total_abonado'   => (float)$c->total_abonado,
                'saldo_pendiente' => $saldo,
            ];
        })
        ->filter(function($c) use ($request) {
            if ($c === null) return false;

            // Si busca pagados, el saldo debe ser 0 o menor
            if ($request->estado === 'PAGADO') {
                return $c['saldo_pendiente'] <= 0;
            }

            // Si es pendiente o seleciona todo, se muestran los que tienen saldos activos
            return $c['saldo_pendiente'] > 0;
        });
    }
}
