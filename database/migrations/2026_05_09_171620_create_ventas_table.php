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
        Schema::create('ventas', function (Blueprint $table) {
            $table->id();
            $table->integer('correlativo')->unique();
            $table->timestamp('fecha');
            $table->decimal('total', 12,2);
            $table->enum('tipo_cliente', ['DETALLES', 'MAYORISTA'])->default('DETALLES');
            $table->enum('estado', ['PAGADA', 'CREDITO', 'ANULADA'])->default('PAGADA');
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users');
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
        Schema::dropIfExists('ventas');
    }
};
