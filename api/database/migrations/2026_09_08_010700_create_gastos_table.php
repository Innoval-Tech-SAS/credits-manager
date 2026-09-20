<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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
            $table->date('fecha');
            $table->string('numero_documento', 60);
            $table->string('concepto', 255);

            $table->decimal('valor_bruto', 14, 2);
            $table->decimal('valor_iva', 14, 2)->default(0);
            $table->decimal('valor_retefuente', 14, 2)->default(0);
            $table->decimal('valor_reteica', 14, 2)->default(0);
            $table->decimal('valor_reteiva', 14, 2)->default(0);
            $table->decimal('valor_neto', 14, 2);

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
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gastos');
    }
};
