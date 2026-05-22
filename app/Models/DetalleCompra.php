<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetalleCompra extends Model
{
    protected $table = 'detalle_compras';
    protected $fillable = [
        'cantidad',
        'precio_unitario',
        'margen_detalle',
        'margen_mayor',
        'subtotal',
        'compra_id',
        'producto_id'
    ];
    protected $casts = [
        'precio_unitario' => 'decimal:2',
        'margen_detalle' => 'decimal:2',
        'margen_mayor' => 'decimal:2',
        'subtotal' => 'decimal:2'
    ];

    public function compra()
    {
        return $this->belongsTo(Compra::class);
    }
    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }
}
