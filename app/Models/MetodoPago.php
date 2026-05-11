<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MetodoPago extends Model
{
    protected $table = 'metodos_pagos';
    protected $fillable = ['nombre'];

    public function ventas()
    {
        return $this->hasMany(Venta::class);
    }
    public function abonos()
    {
        return $this->hasMany(Abono::class);
    }
}
