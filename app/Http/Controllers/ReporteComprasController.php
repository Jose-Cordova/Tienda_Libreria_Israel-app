<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class ReporteComprasController extends Controller
{
    /**
     * Devuelve datos JSON para la vista Vue
     */
    //
    public function comprasDatos(Request $request)
    {
        $request->validate([
            'fecha_inicio'  => 'nullable|date',
            'fecha_fin'     => 'nullable|date|after_or_equal:fecha_inicio',
            'estado'        => 'nullable|in:REGISTRADA,ANULADA',
            'proveedor_id'  => 'nullable|integer|exists:proveedores,id',
        ]);
          // Se reutiliza la consulta para obtener los datos filtrados
        $compras = $this->obtenerCompras($request);
        return response()->json(['compras' => $compras->values()]);
    }

    /**
     * Genera el PDF del reporte de compras con DomPDF
     */
    public function reporteCompras(Request $request)
    {
        $request->validate([
            'fecha_inicio'  => 'nullable|date',
            'fecha_fin'     => 'nullable|date|after_or_equal:fecha_inicio',
            'estado'        => 'nullable|in:REGISTRADA,ANULADA',
            'proveedor_id'  => 'nullable|integer|exists:proveedores,id',
        ]);

        $fechaInicio = $request->fecha_inicio
            ? Carbon::parse($request->fecha_inicio)->format('d/m/Y')
            : 'Inicio';
        $fechaFin = $request->fecha_fin
            ? Carbon::parse($request->fecha_fin)->format('d/m/Y')
            : 'Hoy';
         // Se reutiliza la consulta para obtener los datos filtrado
        $compras = $this->obtenerCompras($request);
        $totalRegistros   = $compras->count();
        $totalRegistradas = $compras->where('estado', 'REGISTRADA')->sum('total');
        $totalAnuladas    = $compras->where('estado', 'ANULADA')->sum('total');
        $totalGeneral     = $totalRegistradas;
        $generadoEn       = Carbon::now()->format('d/m/Y H:i');

        return Pdf::loadView('reportes.compras', compact(
            'compras', 'totalRegistros', 'totalRegistradas',
            'totalAnuladas', 'totalGeneral', 'fechaInicio', 'fechaFin', 'generadoEn'
        ))
        ->setPaper('a4', 'portrait')
        ->stream('reporte_compras.pdf');
    }

    /**
     * Consulta reutilizable para ambos métodos
     */
    private function obtenerCompras(Request $request)
    {
         //Preparamos la consulta
        $query = DB::table('compras as c')
            ->join('proveedores as p', 'c.proveedor_id', '=', 'p.id')
            ->select(
                'c.id',
                'c.numero_factura',
                'c.fecha_registro',
                'c.estado',
                'c.total',
                'p.nombre as proveedor',
                'p.telefono',
                    // Se agrega una subconsulta para contar la cantidad de productos en cada compra
                DB::raw('(SELECT COALESCE(SUM(cantidad), 0) FROM detalle_compras WHERE compra_id = c.id) as productos')
            )
            ->orderBy('c.fecha_registro', 'desc');

        if ($request->estado)       $query->where('c.estado', $request->estado);
        if ($request->proveedor_id) $query->where('c.proveedor_id', $request->proveedor_id);
        if ($request->fecha_inicio) $query->whereDate('c.fecha_registro', '>=', $request->fecha_inicio);
        if ($request->fecha_fin)    $query->whereDate('c.fecha_registro', '<=', $request->fecha_fin);
        // Ejecutamos la consulta y formateamos los resultados
        return $query->get()->map(function ($c) {
            return [
                'numero_factura' => $c->numero_factura,
                'fecha'          => Carbon::parse($c->fecha_registro)->format('d/m/Y H:i'),
                'proveedor'      => $c->proveedor,
                'telefono'       => $c->telefono,
                'estado'         => $c->estado,
                'productos'      => $c->productos,
                'total'          => $c->total,
            ];
        });
    }
}
