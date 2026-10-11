<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plantillas de correo, varias por empresa y tipo (`cobranza`/`facturacion`). `clave_predeterminada` protege en la
 * base la invariante "a lo sumo una predeterminada viva por empresa+tipo": vale `empresa_id-tipo` solo cuando
 * `es_predeterminada` está activa y la fila no está borrada, y el índice único rechaza que dos filas la compartan
 * (los `NULL` de las demás filas no chocan entre sí). Mismo truco que `alertas_metas.usuario_clave`.
 *
 * `empresa_id` no lleva `cascadeOnDelete()`: InnoDB rechaza combinar una FK con acción CASCADE sobre una columna
 * con una columna generada STORED que lea esa misma columna (error 1215). `empresas` nunca se borra físicamente
 * desde la aplicación (soft delete), así que la cascada no hacía falta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plantillas_correo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->string('tipo', 20);
            $table->string('nombre', 120);
            $table->string('asunto', 200);
            $table->longText('cuerpo');
            $table->boolean('es_predeterminada')->default(false);
            $table->foreignId('actualizado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('ultima_actualizacion')->nullable();
            $table->softDeletes('borrado_en');

            $table->string('clave_predeterminada', 60)
                ->storedAs("IF(es_predeterminada AND borrado_en IS NULL, CONCAT(empresa_id, '-', tipo), NULL)")
                ->nullable();
            $table->unique('clave_predeterminada');
            $table->index(['empresa_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plantillas_correo');
    }
};
