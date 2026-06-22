<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductoEmpaque extends Model
{
    protected $table = 'productos_empaques';
    protected $fillable = [
        'factor_convercion',
        'empaque_id',
        'producto_id'
    ];

    public function empaque()
    {
        return $this->belongsTo(Empaque::class);
    }
    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }
}
