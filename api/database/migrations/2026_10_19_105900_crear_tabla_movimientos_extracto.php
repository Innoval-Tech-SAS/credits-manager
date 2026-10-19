<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Una fila por movimiento del extracto bancario, en el orden del archivo (hay descripciones repetidas el
 * mismo día, `orden` evita confundirlas). `estado` se usa para emparejar/enviar a cuenta puente; toda fila
 * nace `pendiente`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimientos_extracto', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->unsignedBigInteger('extracto_bancario_id');
            $table->date('fecha');
            $table->string('descripcion', 255);
            $table->string('sucursal', 80)->nullable();
            $table->string('documento', 30)->nullable();
            $table->bigInteger('valor');
            $table->bigInteger('saldo');
            $table->unsignedInteger('orden');
            $table->string('estado', 20);
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('ultima_actualizacion')->nullable();
            $table->softDeletes('borrado_en');

            $table->unique(['empresa_id', 'id']);
            $table->index(['empresa_id', 'extracto_bancario_id', 'estado'], 'movimientos_extracto_estado_index');
            $table->index(['empresa_id', 'extracto_bancario_id', 'fecha'], 'movimientos_extracto_fecha_index');

            $table->foreign(['empresa_id', 'extracto_bancario_id'])->references(['empresa_id', 'id'])->on('extractos_bancarios');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_extracto');
    }
};
