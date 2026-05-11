<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductoDaniado extends Model
{
    protected $table = 'productos_daniados';
    protected $fillable = [
        'descripcion',
        'cantidad',
        'fecha',
        'costo_unitario',
        'total_perdida',
        'producto_id'
    ];
    protected $casts = [
        'fecha' => 'date',
        'costo_unitario' => 'decimal:2',
        'total_perdida' => 'decimal:2'
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }
}
