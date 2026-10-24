<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Registro de control de cada carga masiva de saldos de apertura (archivo, aceptados/rechazados, errores). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('migraciones_apertura', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('tipo', 40);
            $table->string('archivo_original', 255);
            $table->unsignedInteger('registros_aceptados')->default(0);
            $table->unsignedInteger('registros_rechazados')->default(0);
            $table->json('detalle_errores')->nullable();
            $table->foreignId('usuario_id')->constrained('usuarios');
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('ultima_actualizacion')->nullable();
            $table->softDeletes('borrado_en');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('migraciones_apertura');
    }
};
