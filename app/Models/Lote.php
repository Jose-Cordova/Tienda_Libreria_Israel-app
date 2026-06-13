<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lote extends Model
{
    protected $fillable = [
        'fecha_vencimiento',
        'codigo_lote',
        'fecha_ingreso',
        'cantidad_inicial',
        'cantidad_actual',
        'estado',
        'motivo_inactivo',
        'producto_id',
        'compra_id'
    ];
    protected $casts = [
        'fecha_vencimiento' => 'date',
        'fecha_ingreso' => 'date'
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }
    public function compra()
    {
        return $this->belongsTo(Compra::class);
    }

}
