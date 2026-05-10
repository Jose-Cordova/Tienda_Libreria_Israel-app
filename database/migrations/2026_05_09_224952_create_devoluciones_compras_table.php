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
        Schema::create('devoluciones_compras', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->string('motivo', 255);
            $table->unsignedBigInteger('compra_id');
            $table->foreign('compra_id')->references('id')->on('compras');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('devoluciones_compras');
    }
};
