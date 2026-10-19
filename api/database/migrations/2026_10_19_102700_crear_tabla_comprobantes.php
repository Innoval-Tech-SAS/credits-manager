<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `modulo_origen`/`origen_id` son una asociación polimórfica blanda (string + id, sin FK real): un comprobante
 * puede originarse en Créditos, Gastos, Egresos o Facturación. `ciclo_numeracion` sostiene el reinicio periódico
 * del consecutivo (anual/mensual) sin perder la unicidad histórica, default '' para los tipos sin reinicio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comprobantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('tipo_comprobante_id')->constrained('tipos_comprobante')->restrictOnDelete();
            $table->unsignedInteger('consecutivo');
            $table->string('ciclo_numeracion', 10)->default('');
            $table->foreignId('periodo_id')->constrained('periodos_contables')->restrictOnDelete();
            $table->date('fecha');
            $table->foreignId('tercero_id')->nullable()->constrained('terceros')->nullOnDelete();
            $table->string('concepto', 255)->nullable();
            $table->string('modulo_origen', 40)->nullable();
            $table->unsignedBigInteger('origen_id')->nullable();
            $table->string('documento_soporte', 150)->nullable();
            $table->string('estado', 20)->default('borrador');
            $table->bigInteger('total_debito')->default(0);
            $table->bigInteger('total_credito')->default(0);
            $table->foreignId('creado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->foreignId('contabilizado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamp('contabilizado_en')->nullable();
            $table->foreignId('revisado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamp('revisado_en')->nullable();
            $table->foreignId('anulado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamp('anulado_en')->nullable();
            $table->foreignId('comprobante_reverso_id')->nullable()->constrained('comprobantes')->nullOnDelete();
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('ultima_actualizacion')->nullable();
            $table->softDeletes('borrado_en');

            // Para las FK compuestas que Gastos/Egresos/Facturación usan desde `comprobante_id`.
            $table->unique(['empresa_id', 'id']);
            // Nombre explícito y corto: el que generaría Laravel por las 4 columnas supera el límite de 64
            // caracteres de un identificador en MySQL.
            $table->unique(
                ['empresa_id', 'tipo_comprobante_id', 'ciclo_numeracion', 'consecutivo'],
                'comprobantes_consecutivo_por_ciclo_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comprobantes');
    }
};
