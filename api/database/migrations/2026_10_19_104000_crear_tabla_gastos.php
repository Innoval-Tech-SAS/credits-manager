<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `centro_costo_id` lleva FK compuesta con `empresa_id` (relación financiera: un gasto no puede apuntar al
 * centro de costo de otra empresa) — `centros_costo` ya trae `unique(['empresa_id','id'])` para soportarla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gastos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('tipo_gasto_id')->constrained('tipos_gasto');
            $table->foreignId('tercero_id')->nullable()->constrained('terceros');
            $table->foreignId('municipio_id')->nullable()->constrained('municipios');
            $table->unsignedBigInteger('centro_costo_id')->nullable();
            $table->date('fecha');
            $table->string('numero_documento', 60);
            $table->string('concepto', 255);

            $table->bigInteger('valor_bruto');
            $table->bigInteger('valor_iva')->default(0);
            $table->bigInteger('valor_retefuente')->default(0);
            $table->bigInteger('valor_reteica')->default(0);
            $table->bigInteger('valor_reteiva')->default(0);
            $table->bigInteger('valor_neto');

            $table->foreignId('parametrizacion_tipo_gasto_id')->constrained('parametrizaciones_tipo_gasto');
            $table->foreignId('comprobante_id')->nullable()->constrained('comprobantes');
            $table->foreignId('documento_tercero_id')->nullable()->constrained('documentos_tercero');

            $table->string('estado', 20)->default('causado');
            $table->boolean('ajuste_manual')->default(false);
            $table->text('justificacion_ajuste')->nullable();

            $table->foreignId('creado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->foreignId('anulado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamp('anulado_en')->nullable();

            $table->timestamp('creado_en')->nullable();
            $table->timestamp('ultima_actualizacion')->nullable();
            $table->softDeletes('borrado_en');

            $table->index(['empresa_id', 'estado']);
            $table->index(['empresa_id', 'centro_costo_id']);
            $table->foreign(['empresa_id', 'centro_costo_id'])->references(['empresa_id', 'id'])->on('centros_costo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gastos');
    }
};
