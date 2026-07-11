<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DevolucionVenta extends Model
{
    protected $table = 'devoluciones_ventas';
    protected $fillable = [
        'fecha',
        'motivo',
        'total',
        'estado',
        'venta_id'
    ];
    protected $casts = [
        'fecha' => 'date',
        'total' => 'decimal:2'
    ];

    public function detalleDevolucionVentas()
    {
        return $this->hasMany(DetalleDevolucionVenta::class);
    }
    public function venta()
    {
        return $this->belongsTo(Venta::class);
    }
}
