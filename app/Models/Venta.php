<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Venta extends Model
{
    protected $fillable = [
        'correlativo',
        'fecha',
        'total',
        'tipo_cliente',
        'estado',
        'metodo_pago_id',
        'user_id'
    ];
    protected $casts = [
        'fecha' => 'datetime',
        'total' => 'decimal:2'
    ];

    public function detalleVentas()
    {
        return $this->hasMany(DetalleVenta::class);
    }
    public function devolucionVentas()
    {
        return $this->hasMany(DevolucionVenta::class);
    }
    public function credito()
    {
        return $this->hasOne(Credito::class);
    }
    public function metodoPago()
    {
        return $this->belongsTo(MetodoPago::class);
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
