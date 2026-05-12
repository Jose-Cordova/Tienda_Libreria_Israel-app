<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DevolucionCompra extends Model
{
    protected $table = 'devoluciones_compras';
    protected $fillable = [
        'fecha',
        'motivo',
        'compra_id'
    ];
    protected $casts = [
        'fecha' => 'date'
    ];

    public function detalleDevolucionCompras()
    {
        return $this->hasMany(DetalleDevolucionCompra::class);
    }
    public function compra()
    {
        return $this->belongsTo(Compra::class);
    }
}
