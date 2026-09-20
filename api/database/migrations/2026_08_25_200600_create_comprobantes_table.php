<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comprobantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tipo_comprobante_id')->constrained('tipos_comprobante');
            $table->unsignedInteger('consecutivo');
            $table->foreignId('periodo_id')->constrained('periodos_contables');
            $table->date('fecha');
            $table->foreignId('tercero_id')->nullable()->constrained('terceros')->nullOnDelete();
            $table->string('concepto', 255)->nullable();
            $table->string('modulo_origen', 40)->nullable();
            $table->unsignedBigInteger('origen_id')->nullable();
            $table->string('documento_soporte', 150)->nullable();
            $table->string('estado')->default('borrador');
            $table->decimal('total_debito', 14, 2)->default(0);
            $table->decimal('total_credito', 14, 2)->default(0);
            $table->foreignId('creado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->foreignId('contabilizado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamp('contabilizado_en')->nullable();
            $table->foreignId('anulado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamp('anulado_en')->nullable();
            $table->foreignId('comprobante_reverso_id')->nullable()->constrained('comprobantes')->nullOnDelete();
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('ultima_actualizacion')->nullable();
            $table->softDeletes('borrado_en');
            $table->unique(['tipo_comprobante_id', 'consecutivo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comprobantes');
    }
};
