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
        Schema::create('productos_daniados', function (Blueprint $table) {
            $table->id();
            $table->string('descripcion', 255);
            $table->integer('cantidad');
            $table->date('fecha');
            $table->decimal('costo_unitario', 12,2);
            $table->decimal('total_perdida', 12,2);
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
        Schema::dropIfExists('productos_daniados');
    }
};
