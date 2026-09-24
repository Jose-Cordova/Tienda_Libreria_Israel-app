<?php

    namespace App\Models;

    use Illuminate\Database\Eloquent\Model;
    use Illuminate\Database\Eloquent\Relations\BelongsTo;

    class AjusteStock extends Model
    {
        protected $table = 'ajustes_stock';

        protected $fillable = [
            'producto_id',
            'lote_id',
            'tipo_ajuste',
            'cantidad',
            'stock_anterior',
            'stock_nuevo',
            'motivo',
            'origen'
        ];

        public function producto(): BelongsTo
        {
            return $this->belongsTo(Producto::class, 'producto_id');
        }

        public function lote(): BelongsTo
        {
            return $this->belongsTo(Lote::class, 'lote_id');
        }
    }
