<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periodos_contables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->unsignedSmallInteger('anio');
            $table->unsignedTinyInteger('mes');
            $table->string('estado', 20)->default('abierto');
            $table->foreignId('cerrado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamp('cerrado_en')->nullable();
            $table->text('reabierto_motivo')->nullable();
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('ultima_actualizacion')->nullable();
            $table->softDeletes('borrado_en');

            $table->unique(['empresa_id', 'anio', 'mes']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('periodos_contables');
    }
};
