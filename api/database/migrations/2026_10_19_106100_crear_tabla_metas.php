<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Metas mensuales por empresa y tipo. Las FK compuestas de `metas_asesor`, `metas_cierres` y `alertas_metas`
 * hacia `usuarios` necesitan `unique(['empresa_id', 'id'])` en esa tabla — ya viene incluida en el `create` de
 * `usuarios` (`2026_10_19_010600_create_usuarios_table.php`), así que esta migración no la repite.
 */
return new class extends Migration
{
    public function up(): void
    {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('metas');
    }
};
