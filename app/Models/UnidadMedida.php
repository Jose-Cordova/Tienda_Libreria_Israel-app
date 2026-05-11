<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UnidadMedida extends Model
{
    protected $table = 'unidades_medidas';
    protected $fillable = [
        'nombre',
        'equivalencia'
    ];

    public function productos()
    {
        return $this->hasMany(Producto::class);
    }
}
