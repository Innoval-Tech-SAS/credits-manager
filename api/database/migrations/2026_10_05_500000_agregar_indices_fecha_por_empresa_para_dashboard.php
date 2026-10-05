<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Rangos de fecha por empresa para los agregados del dashboard (colocación, recaudo, gastos por mes). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creditos', function (Blueprint $table) {
            $table->index(['empresa_id', 'fecha_desembolso'], 'creditos_empresa_fecha_desembolso_index');
        });

        Schema::table('pagos', function (Blueprint $table) {
            $table->index(['empresa_id', 'fecha_pago'], 'pagos_empresa_fecha_pago_index');
        });

        Schema::table('gastos', function (Blueprint $table) {
            $table->index(['empresa_id', 'fecha'], 'gastos_empresa_fecha_index');
        });
    }

    public function down(): void
    {
        Schema::table('creditos', function (Blueprint $table) {
            $table->dropIndex('creditos_empresa_fecha_desembolso_index');
        });

        Schema::table('pagos', function (Blueprint $table) {
            $table->dropIndex('pagos_empresa_fecha_pago_index');
        });

        Schema::table('gastos', function (Blueprint $table) {
            $table->dropIndex('gastos_empresa_fecha_index');
        });
    }
};
