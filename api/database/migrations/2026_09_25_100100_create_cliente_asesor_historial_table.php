<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de solo inserción: sin `ultima_actualizacion` ni `borrado_en`. Todas las FK usan restrictOnDelete
 * (no cascade ni nullOnDelete) porque las acciones referenciales no disparan los triggers de la migración
 * siguiente y mutarían una fila inmutable. `asesor_anterior_id` es nulo solo en la asignación inicial.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cliente_asesor_historial', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->restrictOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes')->restrictOnDelete();
            $table->foreignId('asesor_anterior_id')->nullable()->constrained('usuarios')->restrictOnDelete();
            $table->foreignId('asesor_id')->constrained('usuarios')->restrictOnDelete();
            $table->foreignId('asignado_por')->nullable()->constrained('usuarios')->restrictOnDelete();
            $table->string('motivo', 255)->nullable();
            $table->timestamp('creado_en')->nullable();

            $table->index(['empresa_id', 'cliente_id', 'creado_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cliente_asesor_historial');
    }
};
