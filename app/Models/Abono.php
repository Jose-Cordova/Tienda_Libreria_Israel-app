<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Abono extends Model
{
    protected $fillable = [
        'fecha_abono',
        'monto',
        'estado',
        'metodo_pago_id',
        'credito_id'
    ];
    protected $casts = [
        'fecha_abono' => 'date',
        'monto' => 'decimal:2'
    ];

    public function metodoPago()
    {
        return $this->belongsTo(MetodoPago::class);
    }
    public function credito()
    {
        return $this->belongsTo(Credito::class);
    }
}
