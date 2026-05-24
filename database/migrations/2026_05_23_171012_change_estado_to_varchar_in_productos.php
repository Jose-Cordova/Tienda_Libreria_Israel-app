<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Convertir la columna estado de enum a VARCHAR(20)
        DB::statement("ALTER TABLE productos ALTER COLUMN estado TYPE VARCHAR(20)");

        // 2. Eliminar la restricción si ya existe (por seguridad)
        DB::statement("ALTER TABLE productos DROP CONSTRAINT IF EXISTS productos_estado_check");

        // 3. Crear la restricción CHECK para los valores permitidos
        DB::statement("ALTER TABLE productos ADD CONSTRAINT productos_estado_check CHECK (estado IN ('ACTIVO', 'INACTIVO'))");
    }

    public function down(): void
    {
        // Revertir: eliminar la restricción y dejar el campo como VARCHAR sin check
        DB::statement("ALTER TABLE productos DROP CONSTRAINT IF EXISTS productos_estado_check");
        // La columna queda como VARCHAR, no se vuelve a crear el enum porque no es necesario
    }
};
