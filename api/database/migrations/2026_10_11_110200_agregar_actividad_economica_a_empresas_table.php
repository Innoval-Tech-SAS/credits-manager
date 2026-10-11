<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cierra un ciclo de FK con `actividades_economicas` (que ya referencia `empresas`): por eso esta FK
 * se agrega en una migración aparte, posterior a la creación de ambas tablas, y no en la original.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->foreignId('actividad_economica_id')->nullable()->after('digito_verificacion')
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
