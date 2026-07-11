<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Marca extends Model
{
    protected $fillable = ['nombre', 'seccion'];

    public function productos()
    {
        return $this->hasMany(Producto::class);
    }
}
