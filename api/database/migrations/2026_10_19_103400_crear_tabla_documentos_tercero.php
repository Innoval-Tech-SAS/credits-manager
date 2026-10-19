<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `modulo_origen`/`origen_id` son una asociación polimórfica blanda (string + id, sin FK real): el documento
 * puede originarse en Gastos, Egresos o Facturación. `plan_cuenta_id` es de bajo riesgo (solo descriptivo/filtro,
 * no mueve dinero): FK simple sin scope compuesto de empresa, validada por el Service al escribir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentos_tercero', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('tercero_id')->constrained('terceros')->restrictOnDelete();
            $table->string('naturaleza', 20);
            $table->foreignId('plan_cuenta_id')->nullable()->constrained('plan_cuentas')->nullOnDelete();
            $table->string('tipo_documento', 60);
            $table->string('numero_documento', 60);
            $table->date('fecha');
            $table->date('fecha_vencimiento')->nullable();
            $table->string('concepto', 255);
            $table->bigInteger('valor_original');
            $table->bigInteger('saldo');
            $table->string('estado', 20)->default('pendiente');
            $table->string('modulo_origen', 60)->nullable();
            $table->unsignedBigInteger('origen_id')->nullable();
            $table->foreignId('documento_original_id')->nullable()->constrained('documentos_tercero')->nullOnDelete();
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('ultima_actualizacion')->nullable();
            $table->softDeletes('borrado_en');

            // Para la FK compuesta que Facturación usa desde `documentos_facturacion.documento_tercero_id`.
            $table->unique(['empresa_id', 'id']);
            $table->index(
                ['empresa_id', 'tercero_id', 'naturaleza', 'estado'],
                'documentos_tercero_empresa_tercero_naturaleza_estado_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentos_tercero');
    }
};
