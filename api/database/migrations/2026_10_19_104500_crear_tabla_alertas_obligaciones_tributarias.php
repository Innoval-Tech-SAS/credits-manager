<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Una fila por obligación cuando entra en su ventana de alerta (dedup vía `unique(empresa_id, obligacion_id)`).
 * No es solo-inserción estricta: `vista_en` se actualiza cuando el responsable la marca como vista, igual que
 * `alertas_metas`. `generada_en` hace de `creado_en` (ver `CREATED_AT` en el Model), por eso no hay columna
 * `creado_en` aparte.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alertas_obligaciones_tributarias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->unsignedBigInteger('obligacion_id');
            $table->date('fecha_limite');
            $table->smallInteger('dias_restantes');
            $table->timestamp('generada_en');
            $table->timestamp('vista_en')->nullable();
            $table->foreignId('notificacion_id')->nullable()->constrained('notificaciones')->nullOnDelete();
            $table->timestamp('ultima_actualizacion')->nullable();
            $table->softDeletes('borrado_en');

            $table->unique(['empresa_id', 'obligacion_id']);
            $table->index(['empresa_id', 'generada_en']);

            $table->foreign(['empresa_id', 'obligacion_id'], 'alertas_obl_trib_empresa_obligacion_foreign')
                ->references(['empresa_id', 'id'])->on('obligaciones_tributarias')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alertas_obligaciones_tributarias');
    }
};
