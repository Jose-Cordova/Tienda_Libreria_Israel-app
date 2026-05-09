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
        Schema::create('abonos', function (Blueprint $table) {
            $table->id();
            $table->date('fecha_abono');
            $table->decimal('monto', 12,2);
            $table->enum('estado', ['PAGADO', 'ANULADO'])->default('PAGADO');
            $table->unsignedBigInteger('credito_id');
            $table->foreign('credito_id')->references('id')->on('creditos');
            $table->unsignedBigInteger('metodo_pago_id');
            $table->foreign('metodo_pago_id')->references('id')->on('metodos_pagos');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('abonos');
    }
};
