<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cambios_productos', function (Blueprint $table) {
            $table->id();
            $table->string('descripcion', 255);
            $table->integer('cantidad');
            $table->date('fecha');
            $table->decimal('costo_unitario', 12, 2);
            $table->decimal('total_perdida', 12, 2);
            $table->enum('estado_reclamacion', ['PENDIENTE', 'ACEPTADO', 'RECHAZADO', 'ANULADO'])->default('PENDIENTE');
            $table->string('reemplazo', 50)->nullable();
            $table->unsignedBigInteger('producto_id');
            $table->foreign('producto_id')->references('id')->on('productos');
            $table->unsignedBigInteger('producto_reemplazo_id')->nullable();
            $table->foreign('producto_reemplazo_id')->references('id')->on('productos')->nullOnDelete();
            $table->unsignedBigInteger('lote_id')->nullable();
            $table->foreign('lote_id')->references('id')->on('lotes')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cambios_productos');
    }
};