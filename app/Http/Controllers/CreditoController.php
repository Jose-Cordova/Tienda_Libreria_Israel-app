<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Credito;
use App\Models\ClienteCredito;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Models\Abono;
use Illuminate\Validation\ValidationException;
use Barryvdh\DomPDF\Facade\Pdf;

class CreditoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $clientes = ClienteCredito::with(['creditos' => function ($query) {
            $query->select('id', 'cliente_credito_id', 'monto_adeudado', 'saldo');
        }])
        ->withCount('creditos')
        ->withSum('creditos', 'monto_adeudado')
        ->withSum('creditos', 'saldo')
        ->paginate(15);

    $clientes->getCollection()->transform(function ($cliente) {
        $totalDeuda   = (float) $cliente->creditos_sum_monto_adeudado;
        $totalAbonado = (float) $cliente->creditos_sum_saldo;
        $estado       = ($totalAbonado >= $totalDeuda) ? 'SIN DEUDA' : 'CON DEUDA';

        return [
            'id'               => $cliente->id,
            'nombre'           => $cliente->nombre,
            'dui'              => $cliente->dui,
            'creditos_activos' => $cliente->creditos_count,
            'total_deuda'      => $totalDeuda,
            'total_abonado'    => $totalAbonado,
            'estado'           => $estado,
        ];
    });

    return response()->json($clientes);
    }

    public function storeAbono(Request $request, $creditoId)
    {
    $credito = Credito::findOrFail($creditoId);

    if ($credito->saldo >= $credito->monto_adeudado) {
        return response()->json([
            'message' => 'Este crédito ya está completamente pagado.'
        ], 400);
    }

    $pendiente = $credito->monto_adeudado - $credito->saldo;

    $data = $request->validate([
        'monto'          => "required|numeric|min:0.01|max:{$pendiente}",
        'metodo_pago_id' => 'required|exists:metodos_pagos,id',
    ]);

    try {
        DB::beginTransaction();

        $abono = Abono::create([
            'fecha_abono'    => now(),
            'monto'          => $data['monto'],
            'estado'         => 'PAGADO',
            'metodo_pago_id' => $data['metodo_pago_id'],
            'credito_id'     => $credito->id,
        ]);

        $credito->saldo += $data['monto'];

        // Actualizar estado y fecha de cancelación
        if ($credito->saldo >= $credito->monto_adeudado) {
            $credito->estado = 'PAGADO';
            $credito->fecha_cancelada = now();
        }

        $credito->save();
        DB::commit();

    $credito->load('abonos.metodoPago');

    return response()->json([
    'message'    => 'Abono registrado correctamente.',
    'credito'    => $credito,
    'ticket_url' => url("/api/abonos/{$abono->id}/ticket")
    ], 201);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'message' => 'Error al registrar el abono.',
            'error'   => $e->getMessage()
        ], 500);
    }
    }

    public function show(string $clienteId)
    {
    try {
        $cliente = ClienteCredito::findOrFail($clienteId);

        $creditos = Credito::with('abonos.metodoPago')
            ->where('cliente_credito_id', $clienteId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($credito) {
                $abonado   = (float) $credito->saldo; // saldo es el total abonado
                $pendiente = (float) $credito->monto_adeudado - $abonado;
                $estado    = $pendiente <= 0 ? 'PAGADO' : 'PENDIENTE';

                return [
                    'id'              => $credito->id,
                    'fecha'           => $credito->created_at->format('Y-m-d'),
                    'monto_original'  => (float) $credito->monto_adeudado,
                    'abonado'         => $abonado,
                    'saldo_pendiente' => max($pendiente, 0),
                    'estado'          => $estado,
                    'abonos'          => $credito->abonos->map(function ($abono) {
                        return [
                            'id'     => $abono->id,
                            'fecha'  => $abono->fecha_abono,
                            'monto'  => (float) $abono->monto,
                            'metodo' => $abono->metodoPago->nombre ?? 'Sin método',
                            'estado' => $abono->estado,
                        ];
                    }),
                ];
            });

        return response()->json([
            'cliente' => [
                'id'        => $cliente->id,
                'nombre'    => $cliente->nombre,
                'dui'       => $cliente->dui,
                'telefono'  => $cliente->telefono,
                'iniciales' => strtoupper(substr($cliente->nombre, 0, 1)) . strtoupper(substr(strrchr($cliente->nombre, ' ') ?: '', 1, 1)),
            ],
            'creditos' => $creditos
        ], 200);

    } catch (ModelNotFoundException $e) {
        return response()->json(['message' => 'Cliente no encontrado.'], 404);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Error al obtener los créditos del cliente.',
            'error'   => $e->getMessage()
        ], 500);
    }
    }

    /**
     * ANULAR ABONO PARA LA TRAZABILIDAD DEL ABONO
     */

    public function ticketAbono($abonoId)
{
    // Datos de la tienda
    $config = DB::table('configuracion')->first();

    // Datos del abono con números secuenciales (alias explícitos)
    $abono = DB::table('abonos as a')
                ->join('creditos as c', 'a.credito_id', '=', 'c.id')
                ->join('clientes_creditos as cc', 'c.cliente_credito_id', '=', 'cc.id')
                ->join('metodos_pagos as mp', 'a.metodo_pago_id', '=', 'mp.id')
                ->where('a.id', $abonoId)
                ->select(
                    'a.id',
                    'a.fecha_abono',
                    'a.monto',
                    'a.estado',
                    'mp.nombre as metodo_pago',
                    'cc.nombre as cliente_nombre',
                    'c.id as credito_id',
                    'c.monto_adeudado',
                    'c.saldo as saldo_actual',
                    // Número secuencial del crédito (dentro del mismo cliente)
                    DB::raw('(SELECT COUNT(*) FROM creditos WHERE creditos.cliente_credito_id = c.cliente_credito_id AND (creditos.created_at, creditos.id) <= (c.created_at, c.id)) as credito_numero'),
                    // Número secuencial del abono (dentro del mismo crédito)
                    DB::raw('(SELECT COUNT(*) FROM abonos WHERE abonos.credito_id = a.credito_id AND (abonos.fecha_abono, abonos.id) <= (a.fecha_abono, a.id)) as abono_numero')
                )
                ->first();

    if (!$abono) {
        abort(404, 'Abono no encontrado');
    }

    $pdf = Pdf::loadView('tickets.abono', compact('config', 'abono'));

    $pdf->setPaper([0, 0, 226.77, 300], 'portrait');

    return $pdf->stream("ticket-abono-{$abono->abono_numero}.pdf");
}

}
