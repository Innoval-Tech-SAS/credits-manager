<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `ciclo_numeracion_actual` arranca en '' (no null) a propósito: es lo que calcula `calcularCiclo(Nunca)`
 * para los tipos existentes, así la primera asignación después de migrar no detecta un "cambio de ciclo"
 * espurio que reiniciaría `consecutivo_actual` y chocaría con los comprobantes ya creados.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tipos_comprobante', function (Blueprint $table) {
            $table->unsignedInteger('consecutivo_inicial')->default(1)->after('consecutivo_actual');
            $table->unsignedInteger('consecutivo_final')->nullable()->after('consecutivo_inicial');
            $table->string('tipo_numeracion', 20)->default('automatico')->after('consecutivo_final');
            $table->string('reinicio_periodo', 20)->default('nunca')->after('tipo_numeracion');
            $table->string('ciclo_numeracion_actual', 10)->default('')->after('reinicio_periodo');
        });
    }

    public function down(): void
    {
        Schema::table('tipos_comprobante', function (Blueprint $table) {
            $table->dropColumn([
                'consecutivo_inicial',
                'consecutivo_final',
                'tipo_numeracion',
                'reinicio_periodo',
                'ciclo_numeracion_actual',
            ]);
        });
    }
};
