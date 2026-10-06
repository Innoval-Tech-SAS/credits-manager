<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El centro de costo deja de ser opcional en los gastos nuevos (DDA lo habilitó). La columna es anulable porque los
 * gastos ya causados no tienen un centro que se pueda deducir; el reporte los muestra como «Sin centro de costo».
 * La FK es compuesta con la empresa: un gasto no puede apuntar al centro de otra empresa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('centros_costo', function (Blueprint $table) {
            $table->unique(['empresa_id', 'id']);
        });

        Schema::table('gastos', function (Blueprint $table) {
            $table->unsignedBigInteger('centro_costo_id')->nullable()->after('municipio_id');
            $table->index(['empresa_id', 'centro_costo_id']);
            $table->foreign(['empresa_id', 'centro_costo_id'])->references(['empresa_id', 'id'])->on('centros_costo');
        });
    }

    public function down(): void
    {
        Schema::table('gastos', function (Blueprint $table) {
            $table->dropForeign(['empresa_id', 'centro_costo_id']);
            $table->dropIndex(['empresa_id', 'centro_costo_id']);
            $table->dropColumn('centro_costo_id');
        });

        Schema::table('centros_costo', function (Blueprint $table) {
            $table->dropUnique(['empresa_id', 'id']);
        });
    }
};
