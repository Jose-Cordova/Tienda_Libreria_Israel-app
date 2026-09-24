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
            Schema::create('ajustes_stock', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('producto_id');
                $table->foreign('producto_id')->references('id')->on('productos')->onDelete('cascade');
                $table->unsignedBigInteger('lote_id')->nullable();
                $table->foreign('lote_id')->references('id')->on('lotes')->nullOnDelete();
                $table->enum('tipo_ajuste', ['INCREMENTO', 'DECREMENTO']);
                $table->integer('cantidad');
                $table->integer('stock_anterior');
                $table->integer('stock_nuevo');
                $table->string('motivo', 255);
                $table->timestamps();
            });
        }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ajustes_stock');
    }
};
