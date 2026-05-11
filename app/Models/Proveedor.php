<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Proveedor extends Model
{
    protected $table = 'proveedores';
    protected $fillable = [
        'nombre',
        'telefono',
        'email',
        'direccion'
    ];

    public function compras()
    {
        return $this->hasMany(Compra::class);
    }
    public function cronogramaProveedores()
    {
        return $this->hasMany(CronogramaProveedor::class);
    }
}
