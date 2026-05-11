<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Credito extends Model
{
    protected $fillable = [
        'monto_adeudado',
        'saldo',
        'fecha_cancelada',
        'cliente_credito_id',
        'venta_id'
    ];
    protected $casts = [
        'monto_adeudado' => 'decimal:2',
        'saldo' => 'decimal:2',
        'fecha_cancelada' => 'date'
    ];

    public function abonos()
    {
        return $this->hasMany(Abono::class);
    }
    public function clienteCredito()
    {
        return $this->belongsTo(ClienteCredito::class);
    }
    public function venta()
    {
        return $this->belongsTo(Venta::class);
    }
}
