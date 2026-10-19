<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo editable por empresa de naturaleza/crea_saldo/cruza_saldo por tipo de documento de negocio
 * (FV/CC/RC/CE/NC/ND). `TipoDocumentoNegocioService::validarOperacion()` la consulta en tiempo de ejecución
 * antes de crear o cruzar un `DocumentoTercero`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_documento_negocio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('codigo', 2);
            $table->string('nombre', 100);
            $table->string('naturaleza', 20);
            $table->boolean('crea_saldo')->default(false);
            $table->boolean('cruza_saldo')->default(false);
            $table->boolean('activo')->default(true);
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('ultima_actualizacion')->nullable();
            $table->softDeletes('borrado_en');

            $table->unique(['empresa_id', 'codigo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_documento_negocio');
    }
};
