<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Metas mensuales por empresa y tipo, su distribución por asesor y los parámetros de cada empresa.
 * Las FK compuestas con `empresa_id` impiden en la base que una meta o un asesor de otra empresa se asocie.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->unique(['empresa_id', 'id']);
        });

        Schema::create('metas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('periodo', 7);
            $table->string('tipo', 20);
            $table->bigInteger('valor_objetivo');
            $table->foreignId('creado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('ultima_actualizacion')->nullable();
            $table->softDeletes('borrado_en');

            $table->unique(['empresa_id', 'periodo', 'tipo']);
            $table->unique(['empresa_id', 'id']);
        });

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

        Schema::create('metas_configuracion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('clave', 60);
            $table->string('valor', 60);
            $table->foreignId('actualizado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('ultima_actualizacion')->nullable();
            $table->softDeletes('borrado_en');

            $table->unique(['empresa_id', 'clave']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metas_configuracion');
        Schema::dropIfExists('metas_asesor');
        Schema::dropIfExists('metas');

        // El índice compuesto también sirve a la FK usuarios.empresa_id: MySQL no lo suelta sin otro índice que la respalde.
        if (! Schema::hasIndex('usuarios', ['empresa_id'])) {
            Schema::table('usuarios', function (Blueprint $table) {
                $table->index('empresa_id');
            });
        }

        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropUnique(['empresa_id', 'id']);
        });
    }
};
