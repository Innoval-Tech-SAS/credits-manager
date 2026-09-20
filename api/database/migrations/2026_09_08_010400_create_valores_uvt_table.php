<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('valores_uvt', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('anio')->unique();
            $table->decimal('valor', 10, 2);
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('ultima_actualizacion')->nullable();
            $table->softDeletes('borrado_en');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('valores_uvt');
    }
};
