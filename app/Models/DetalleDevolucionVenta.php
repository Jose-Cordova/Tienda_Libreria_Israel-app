<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetalleDevolucionVenta extends Model
{
    protected $table = 'detalle_devoluciones_ventas';
    protected $fillable = [
        'cantidad',
        'precio_unitario',
        'subtotal',
        'condicion',
        'devolucion_venta_id',
        'producto_id'
    ];
    protected $casts = [
        'precio_unitario' => 'decimal:2',
        'subtotal' => 'decimal:2'
    ];

    public function devolucionVenta()
    {
        return $this->belongsTo(DevolucionVenta::class);
    }
    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }
}
