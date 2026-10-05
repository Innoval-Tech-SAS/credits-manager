<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cierres mensuales de metas (solo inserción: un recálculo agrega una fila que apunta a la anterior) y alertas.
 * `usuario_clave` existe porque MySQL admite varios NULL en un índice único: así la alerta de empresa
 * (sin asesor) también se deduplica. `usuario_id` nulo en el cierre o la alerta significa la meta de empresa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metas_cierres', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->unsignedBigInteger('meta_id');
            $table->string('periodo', 7);
            $table->string('tipo', 20);
            $table->unsignedBigInteger('asesor_id')->nullable();
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->restrictOnDelete();
            $table->foreignId('cierre_anterior_id')->nullable()->constrained('metas_cierres')->restrictOnDelete();
            $table->bigInteger('objetivo');
            $table->bigInteger('ejecutado');
            $table->decimal('porcentaje', 7, 2);
            $table->string('estado', 30);
            $table->string('motivo', 255)->nullable();
            $table->timestamp('cerrado_en');

            $table->index(['empresa_id', 'periodo', 'tipo']);
            $table->index(['empresa_id', 'meta_id', 'asesor_id']);
            $table->foreign(['empresa_id', 'meta_id'])->references(['empresa_id', 'id'])->on('metas')->restrictOnDelete();
            $table->foreign(['empresa_id', 'asesor_id'])->references(['empresa_id', 'id'])->on('usuarios')->restrictOnDelete();
        });

        Schema::create('alertas_metas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->unsignedBigInteger('meta_id');
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->unsignedBigInteger('usuario_clave')->storedAs('ifnull(usuario_id, 0)');
            $table->string('periodo', 7);
            $table->string('estado', 30);
            $table->timestamp('generada_en');
            $table->timestamp('vista_en')->nullable();
            $table->timestamp('ultima_actualizacion')->nullable();
            $table->softDeletes('borrado_en');

            $table->unique(['meta_id', 'usuario_clave', 'estado', 'periodo']);
            $table->index(['empresa_id', 'generada_en']);
            $table->foreign(['empresa_id', 'meta_id'])->references(['empresa_id', 'id'])->on('metas')->restrictOnDelete();
            $table->foreign(['empresa_id', 'usuario_id'])->references(['empresa_id', 'id'])->on('usuarios')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alertas_metas');
        Schema::dropIfExists('metas_cierres');
    }
};
