<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Instancias del calendario tributario. `estado` solo guarda pendiente/cumplida/no_aplica: "vencida" no se
 * persiste, se calcula al servir el recurso comparando `fecha_limite` con hoy (evita un derivado sin invariante).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('obligaciones_tributarias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->unsignedBigInteger('tipo_obligacion_id');
            $table->string('periodo', 20);
            $table->date('fecha_limite');
            $table->unsignedBigInteger('responsable_id')->nullable();
            $table->string('estado', 20)->default('pendiente');
            $table->string('observaciones', 500)->nullable();
            $table->unsignedSmallInteger('dias_alerta_previa')->nullable();
            $table->timestamp('cumplida_en')->nullable();
            $table->foreignId('cumplida_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->foreignId('creado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('ultima_actualizacion')->nullable();
            $table->softDeletes('borrado_en');

            $table->unique(['empresa_id', 'tipo_obligacion_id', 'periodo'], 'obl_trib_empresa_tipo_periodo_unique');
            $table->unique(['empresa_id', 'id']);
            $table->index(['empresa_id', 'fecha_limite']);
            $table->index(['empresa_id', 'estado']);

            $table->foreign(['empresa_id', 'tipo_obligacion_id'])
                ->references(['empresa_id', 'id'])->on('tipos_obligacion_tributaria')->restrictOnDelete();
            $table->foreign(['empresa_id', 'responsable_id'])
                ->references(['empresa_id', 'id'])->on('usuarios')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('obligaciones_tributarias');
    }
};
