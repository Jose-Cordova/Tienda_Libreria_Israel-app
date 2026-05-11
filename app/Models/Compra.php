<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Compra extends Model
{
    protected $fillable = [
        'fecha_registro',
        'numero_factura',
        'codigo_factura',
        'fecha_emision',
        'total',
        'estado',
        'user_id',
        'proveedor_id'
    ];
    protected $casts = [
        'fecha_registro' => 'date',
        'fecha_emision' => 'date',
        'total' => 'decimal:2'
    ];

    public function detalleCompras()
    {
        return $this->hasMany(DetalleCompra::class);
    }
    public function devolucionCompras()
    {
        return $this->hasMany(DevolucionCompra::class);
    }
    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class);
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
