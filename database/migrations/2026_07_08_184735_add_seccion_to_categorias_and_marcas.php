<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categorias', function (Blueprint $table) {
            $table->enum('seccion', ['TIENDA', 'LIBRERIA', 'MEDICAMENTO'])->after('nombre');
        });

        Schema::table('marcas', function (Blueprint $table) {
            $table->enum('seccion', ['TIENDA', 'LIBRERIA', 'MEDICAMENTO'])->after('nombre');
        });
    }

    public function down(): void
    {
        Schema::table('categorias', function (Blueprint $table) {
            $table->dropColumn('seccion');
        });

        Schema::table('marcas', function (Blueprint $table) {
            $table->dropColumn('seccion');
        });
    }
};
