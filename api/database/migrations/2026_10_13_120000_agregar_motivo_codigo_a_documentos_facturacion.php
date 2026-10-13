<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `motivo` solo guarda la etiqueta ya resuelta o el texto libre de «otros»: no alcanza para filtrar notas por
 * motivo con un select, porque la etiqueta puede repetirse entre nota crédito y nota débito y «otros» no tiene
 * valor fijo. `motivo_codigo` guarda el código cerrado (`MotivoNotaCredito`/`MotivoNotaDebito`) y sí es filtrable.
 *
 * El backfill solo puede reconocer las filas cuyo `motivo` coincide exactamente con una etiqueta fija; las notas
 * crédito de anulación automática (`DocumentoFacturacionService::anular()`) guardaban el texto libre que escribió
 * el usuario, no la etiqueta "Anulación", así que quedan como `otros` si no hay coincidencia — una imprecisión
 * aceptada para datos históricos, sin impacto en notas nuevas (que sí guardan el código desde que se emiten).
 */
return new class extends Migration
{
    private const ETIQUETAS_POR_CODIGO = [
        'devolucion' => 'Devolución',
        'anulacion' => 'Anulación',
        'rebaja_descuento' => 'Rebaja o descuento',
        'ajuste_precio' => 'Ajuste de precio',
        'intereses' => 'Intereses',
        'gastos_por_cobrar' => 'Gastos por cobrar',
        'cambio_valor' => 'Cambio del valor',
    ];

    public function up(): void
    {
        Schema::table('documentos_facturacion', function (Blueprint $table) {
            $table->string('motivo_codigo', 30)->nullable()->after('motivo');
            $table->index(['empresa_id', 'motivo_codigo']);
        });

        foreach (self::ETIQUETAS_POR_CODIGO as $codigo => $etiqueta) {
            DB::table('documentos_facturacion')->where('motivo', $etiqueta)->update(['motivo_codigo' => $codigo]);
        }

        DB::table('documentos_facturacion')
            ->whereIn('tipo', ['nota_credito', 'nota_debito'])
            ->whereNotNull('motivo')
            ->whereNull('motivo_codigo')
            ->update(['motivo_codigo' => 'otros']);
    }

    public function down(): void
    {
        Schema::table('documentos_facturacion', function (Blueprint $table) {
            $table->dropIndex(['empresa_id', 'motivo_codigo']);
            $table->dropColumn('motivo_codigo');
        });
    }
};
