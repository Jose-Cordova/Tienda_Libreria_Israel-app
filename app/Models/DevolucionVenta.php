<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DevolucionVenta extends Model
{
    protected $table = 'devoluciones_ventas';
    protected $fillable = [
        'fecha',
        'motivo',
        'venta_id'
    ];
    protected $casts = [
        'fecha' => 'date'
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
