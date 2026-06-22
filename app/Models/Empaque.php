<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Empaque extends Model
{
    protected $fillable = [
        'nombre'
    ];

    public function productoEmpaques()
    {
        return $this->hasMany(ProductoEmpaque::class);
    }

}
