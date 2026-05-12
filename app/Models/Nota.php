<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Nota extends Model
{
    protected $fillable = [
        'fecha',
        'contenido',
        'user_id'
    ];
    protected $casts = [
        'fecha' => 'date'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
