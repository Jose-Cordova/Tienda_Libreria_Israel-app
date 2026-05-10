<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('detalle_devoluciones_compras', function (Blueprint $table) {
            $table->id();
            $table->integer('cantidad');
            $table->decimal('precio_unitario', 12,2);
            $table->decimal('subtotal', 12,2);
            $table->unsignedBigInteger('devolucion_id');
            $table->foreign('devolucion_id')->references('id')->on('devoluciones_compras');
            $table->unsignedBigInteger('producto_id');
            $table->foreign('producto_id')->references('id')->on('productos');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detalle_devoluciones_compras');
    }
};
