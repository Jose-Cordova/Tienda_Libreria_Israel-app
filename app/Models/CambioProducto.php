<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CambioProducto extends Model
{
    protected $table = 'cambios_productos';

    protected $fillable = [
        'descripcion',
        'cantidad',
        'fecha',
        'costo_unitario',
        'total_perdida',
        'estado',
        'reemplazo',
        'producto_id',
        'producto_reemplazo_id',
        'lote_id',
    ];

    protected $casts = [
        'fecha' => 'date',
        'costo_unitario' => 'decimal:2',
        'total_perdida' => 'decimal:2',
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function productoReemplazo()
    {
        return $this->belongsTo(Producto::class, 'producto_reemplazo_id');
    }

    public function lote()
    {
        return $this->belongsTo(Lote::class);
    }
}