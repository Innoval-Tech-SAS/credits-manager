<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alertas de cumplimiento/incumplimiento de una meta. `usuario_clave` existe porque MySQL admite varios NULL
 * en un índice único: así la alerta de empresa (sin asesor) también se deduplica. `usuario_id` nulo significa
 * la meta de empresa. `generada_en` hace de marca de tiempo de creación; `vista_en` se actualiza cuando el
 * usuario la revisa, así que la tabla sí lleva `ultima_actualizacion` y soft delete.
 */
return new class extends Migration
{
    public function up(): void
    {
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
    }
};
