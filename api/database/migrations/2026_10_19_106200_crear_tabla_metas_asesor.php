<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Distribución de una meta de empresa entre sus asesores. Las FK compuestas con `empresa_id` impiden en la base que una meta o un usuario de otra empresa se asocien. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metas_asesor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->unsignedBigInteger('meta_id');
            $table->unsignedBigInteger('usuario_id');
            $table->bigInteger('valor_objetivo');
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('ultima_actualizacion')->nullable();
            $table->softDeletes('borrado_en');

            $table->unique(['meta_id', 'usuario_id']);
            $table->index(['empresa_id', 'meta_id']);
            $table->index(['empresa_id', 'usuario_id']);
            $table->foreign(['empresa_id', 'meta_id'])->references(['empresa_id', 'id'])->on('metas')->restrictOnDelete();
            $table->foreign(['empresa_id', 'usuario_id'])->references(['empresa_id', 'id'])->on('usuarios')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metas_asesor');
    }
};
