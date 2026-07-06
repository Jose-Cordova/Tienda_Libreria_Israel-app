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
        'producto_id',
        'detalle_venta_id',
        'producto_daniado_id'
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
    public function detalleVenta()
    {
        return $this->belongsTo(DetalleVenta::class, 'detalle_venta_id');
    }
    
    public function productoDaniado()
    {
        return $this->belongsTo(ProductoDaniado::class, 'producto_daniado_id');
    }
}
