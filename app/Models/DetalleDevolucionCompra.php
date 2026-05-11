<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetalleDevolucionCompra extends Model
{
    protected $table = 'detalle_devoluciones_compras';
    protected $fillable = [
        'cantidad',
        'precio_unitario',
        'subtotal',
        'devolucion_compra_id',
        'producto_id'
    ];
    protected $casts = [
        'precio_unitario' => 'decimal:2',
        'subtotal' => 'decimal:2'
    ];

    public function devolucionCompra()
    {
        return $this->belongsTo(DevolucionCompra::class);
    }
    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }
}
