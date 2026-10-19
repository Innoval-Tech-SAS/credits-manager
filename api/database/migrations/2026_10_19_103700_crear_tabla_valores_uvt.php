<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** La UVT la fija la DIAN cada año, pero cada empresa la administra por su cuenta (un admin no altera la de otra). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('valores_uvt', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->unsignedSmallInteger('anio');
            $table->bigInteger('valor');
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('ultima_actualizacion')->nullable();
            $table->softDeletes('borrado_en');

            $table->unique(['empresa_id', 'anio']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('valores_uvt');
    }
};
