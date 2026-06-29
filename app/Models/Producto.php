<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    protected $fillable = [
        'nombre',
        'precio_detalle',
        'precio_mayor',
        'stock',
        'stock_minimo',
        'sesion',
        'perecedero',
        'estado',
        'marca_id',
        'categoria_id'
    ];
    protected $casts = [
        'precio_detalle' => 'decimal:2',
        'precio_mayor' => 'decimal:2'
    ];

    public function detalleVentas()
    {
        return $this->hasMany(DetalleVenta::class);
    }
    public function detalleDevolucionVentas()
    {
        return $this->hasMany(DetalleDevolucionVenta::class);
    }
    public function detalleDevolucionCompras()
    {
        return $this->hasMany(DetalleDevolucionCompra::class);
    }
    public function detalleCompras()
    {
        return $this->hasMany(DetalleCompra::class);
    }
    public function lotes()
    {
        return $this->hasMany(Lote::class);
    }
    public function productoDaniados()
    {
        return $this->hasMany(ProductoDaniado::class);
    }
    public function marca()
    {
        return $this->belongsTo(Marca::class);
    }
    public function categoria()
    {
        return $this->belongsTo(Categoria::class);
    }
}
