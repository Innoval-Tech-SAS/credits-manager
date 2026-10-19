<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Catálogo de obligaciones tributarias configurable por empresa (IVA, RteICA, renta...), igual patrón que `tipos_gasto`. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_obligacion_tributaria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('nombre', 120);
            $table->string('periodicidad', 20);
            $table->unsignedSmallInteger('dias_alerta_previa')->default(5);
            $table->boolean('activo')->default(true);
            $table->foreignId('creado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('ultima_actualizacion')->nullable();
            $table->softDeletes('borrado_en');

            $table->unique(['empresa_id', 'nombre']);
            // Para la FK compuesta que `obligaciones_tributarias.tipo_obligacion_id` usa más adelante.
            $table->unique(['empresa_id', 'id']);
            $table->index(['empresa_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_obligacion_tributaria');
    }
};
