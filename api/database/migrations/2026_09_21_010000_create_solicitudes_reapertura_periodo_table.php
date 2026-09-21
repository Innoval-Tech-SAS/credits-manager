<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Solicitud del contador para reabrir un período cerrado; la autoriza o rechaza el administrador.
 * Una sola solicitud pendiente por período: MySQL no tiene índices únicos parciales, así que esa
 * regla la garantiza el Service dentro de la transacción, no la base.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitudes_reapertura_periodo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('periodo_id')->constrained('periodos_contables')->cascadeOnDelete();
            $table->foreignId('solicitado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->text('motivo');
            $table->string('estado', 20)->default('pendiente');
            $table->foreignId('resuelto_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamp('resuelto_en')->nullable();
            $table->text('motivo_resolucion')->nullable();
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('ultima_actualizacion')->nullable();
            $table->softDeletes('borrado_en');

            $table->index(['empresa_id', 'estado']);
            $table->index(['empresa_id', 'periodo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes_reapertura_periodo');
    }
};
