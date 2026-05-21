<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClienteCredito extends Model
{
    protected $table = 'clientes_creditos';
    protected $fillable = [
    'nombre',
    'dui',
    'telefono'
];

    public function creditos()
    {
        return $this->hasMany(Credito::class);
    }
}
