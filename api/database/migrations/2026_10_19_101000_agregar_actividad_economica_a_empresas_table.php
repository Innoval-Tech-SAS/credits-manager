<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Se agrega aquí y no en el `create` de `empresas` porque `actividades_economicas` tiene `empresa_id` → `empresas`:
 * crearla primero habría cerrado un ciclo de FK dentro de una sola migración.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->foreignId('actividad_economica_id')->nullable()
                ->constrained('actividades_economicas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropForeign(['actividad_economica_id']);
            $table->dropColumn('actividad_economica_id');
        });
    }
};
