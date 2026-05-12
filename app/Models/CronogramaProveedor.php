<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CronogramaProveedor extends Model
{
    protected $table = 'cronograma_proveedores';
    protected $fillable = [
        'fecha',
        'contenido',
        'proveedor_id'
    ];
    protected $casts = [
        'fecha' => 'date'
    ];

    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class);
    }
}
