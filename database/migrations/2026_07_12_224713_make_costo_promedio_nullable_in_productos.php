<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->decimal('costo_promedio', 12, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('productos')->whereNull('costo_promedio')->update(['costo_promedio' => 0]);

        Schema::table('productos', function (Blueprint $table) {
            $table->decimal('costo_promedio', 12, 2)->nullable(false)->change();
        });
    }
};
