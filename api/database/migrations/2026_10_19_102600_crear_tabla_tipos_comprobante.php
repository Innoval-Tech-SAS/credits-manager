<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `ciclo_numeracion_actual` arranca en '' (no null) a propósito: así el primer cálculo de ciclo no detecta
 * un "cambio de ciclo" espurio contra un tipo recién creado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_comprobante', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('codigo', 10);
            $table->string('nombre', 100);
            $table->boolean('activo')->default(true);
            $table->unsignedInteger('consecutivo_actual')->default(0);
            $table->unsignedInteger('consecutivo_inicial')->default(1);
            $table->unsignedInteger('consecutivo_final')->nullable();
            $table->string('tipo_numeracion', 20)->default('automatico');
            $table->string('reinicio_periodo', 20)->default('nunca');
            $table->string('ciclo_numeracion_actual', 10)->default('');
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('ultima_actualizacion')->nullable();
            $table->softDeletes('borrado_en');

            $table->unique(['empresa_id', 'codigo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_comprobante');
    }
};
