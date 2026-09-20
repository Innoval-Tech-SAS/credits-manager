<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exportaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('usuario_id')->constrained('usuarios');

            $table->string('reporte', 60);
            $table->string('formato', 10);
            $table->string('estado', 20)->default('pendiente');
            $table->json('filtros')->nullable();

            $table->unsignedInteger('filas')->default(0);
            $table->string('nombre_archivo')->nullable();
            $table->string('ruta_archivo')->nullable();
            $table->unsignedBigInteger('tamano_bytes')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('iniciada_en')->nullable();
            $table->timestamp('completada_en')->nullable();

            $table->timestamp('creado_en')->nullable();
            $table->timestamp('ultima_actualizacion')->nullable();
            $table->softDeletes('borrado_en');

            $table->index(['empresa_id', 'usuario_id', 'creado_en']);
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exportaciones');
    }
};
