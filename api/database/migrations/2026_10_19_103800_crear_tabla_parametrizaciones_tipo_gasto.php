<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vigencia por tipo de gasto (`fecha_desde`/`fecha_hasta`) con la cuenta contable y la tarifa fiscal aplicable
 * en cada ventana. Las tarifas son porcentajes (`decimal`), no dinero.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parametrizaciones_tipo_gasto', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('tipo_gasto_id')->constrained('tipos_gasto')->cascadeOnDelete();
            $table->date('fecha_desde');
            $table->date('fecha_hasta')->nullable();

            $table->foreignId('tipo_comprobante_id')->constrained('tipos_comprobante');
            $table->foreignId('cuenta_gasto_id')->constrained('plan_cuentas');
            $table->foreignId('cuenta_iva_id')->nullable()->constrained('plan_cuentas');
            $table->decimal('tarifa_iva', 6, 3)->nullable();
            $table->foreignId('cuenta_cxp_id')->constrained('plan_cuentas');

            $table->boolean('aplica_retefuente')->default(false);
            $table->string('concepto_dian', 150)->nullable();
            $table->unsignedInteger('base_minima_uvt')->nullable();
            $table->decimal('tarifa_retefuente_persona_natural', 6, 3)->nullable();
            $table->decimal('tarifa_retefuente_persona_juridica', 6, 3)->nullable();
            $table->foreignId('cuenta_retefuente_id')->nullable()->constrained('plan_cuentas');

            $table->boolean('aplica_reteica')->default(false);
            $table->foreignId('cuenta_reteica_id')->nullable()->constrained('plan_cuentas');

            $table->boolean('aplica_reteiva')->default(false);
            $table->decimal('tarifa_reteiva', 6, 3)->nullable();
            $table->foreignId('cuenta_reteiva_id')->nullable()->constrained('plan_cuentas');

            $table->timestamp('creado_en')->nullable();
            $table->timestamp('ultima_actualizacion')->nullable();
            $table->softDeletes('borrado_en');

            $table->index(['empresa_id', 'tipo_gasto_id', 'fecha_desde'], 'parametrizaciones_tipo_gasto_vigencia_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parametrizaciones_tipo_gasto');
    }
};
