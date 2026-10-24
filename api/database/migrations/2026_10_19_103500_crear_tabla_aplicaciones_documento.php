<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sin `empresa_id` propio: se segrega vía `documento_tercero_id` → `documentos_tercero.empresa_id`.
 * `modulo_aplicacion`/`aplicacion_id` son una asociación polimórfica blanda (string + id, sin FK real).
 * `reversado_en` marca cuándo una aplicación fue deshecha por una reversa, sin lo cual una aplicación ya
 * reversada bloquearía para siempre la anulación de su documento origen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aplicaciones_documento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('documento_tercero_id')->constrained('documentos_tercero')->cascadeOnDelete();
            $table->string('modulo_aplicacion', 60);
            $table->unsignedBigInteger('aplicacion_id')->nullable();
            $table->bigInteger('valor_aplicado');
            $table->date('fecha');
            $table->timestamp('reversado_en')->nullable();
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('ultima_actualizacion')->nullable();
            $table->softDeletes('borrado_en');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aplicaciones_documento');
    }
};
