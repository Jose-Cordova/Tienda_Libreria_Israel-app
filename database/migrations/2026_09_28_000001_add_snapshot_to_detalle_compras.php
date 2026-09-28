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
        //Foto del producto antes de la compra para poder revertirla al anular
        Schema::table('detalle_compras', function (Blueprint $table) {
            $table->decimal('cpp_anterior', 12, 2)->nullable();
            $table->decimal('precio_detalle_anterior', 12, 2)->nullable();
            $table->decimal('precio_mayor_anterior', 12, 2)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('detalle_compras', function (Blueprint $table) {
            $table->dropColumn(['cpp_anterior', 'precio_detalle_anterior', 'precio_mayor_anterior']);
        });
    }
};
