<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos_daniados', function (Blueprint $table) {
            $table->id();
            $table->string('descripcion', 255);
            $table->integer('cantidad');
            $table->date('fecha');
            $table->decimal('costo_unitario', 12, 2);
            $table->decimal('total_perdida', 12, 2);

            // Origen del registro: de dónde viene la pérdida
            $table->enum('origen', ['DIRECTO', 'VENCIMIENTO', 'VENTA', 'PROVEEDOR'])->default('DIRECTO');

            // Estado del flujo de reclamación al proveedor
            $table->enum('estado_reclamacion', ['REGISTRADO', 'RECHAZADO', 'ANULADO'])->default('REGISTRADO');

            $table->unsignedBigInteger('producto_id');
            $table->foreign('producto_id')->references('id')->on('productos');

            $table->unsignedBigInteger('lote_id')->nullable();
            $table->foreign('lote_id')->references('id')->on('lotes')->nullOnDelete();

            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('productos_daniados');
    }

};
