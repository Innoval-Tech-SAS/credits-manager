<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('importacion_errores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('importacion_id')->constrained('importaciones')->cascadeOnDelete();

            $table->unsignedInteger('fila')->nullable();
            $table->string('columna', 100)->nullable();
            $table->string('severidad', 10)->default('error');
            $table->string('fase', 15);
            $table->string('motivo', 500);

            $table->timestamp('creado_en')->nullable();
            $table->timestamp('ultima_actualizacion')->nullable();
            $table->softDeletes('borrado_en');

            $table->index(['importacion_id', 'fila']);
            $table->index(['importacion_id', 'severidad']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('importacion_errores');
    }
};
