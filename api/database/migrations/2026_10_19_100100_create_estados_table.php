<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pais_id')->constrained('paises');
            $table->string('codigo', 10);
            $table->string('nombre', 150);
            $table->boolean('activo')->default(true);
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('ultima_actualizacion')->nullable();
            $table->softDeletes('borrado_en');

            $table->unique(['pais_id', 'codigo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estados');
    }
};
