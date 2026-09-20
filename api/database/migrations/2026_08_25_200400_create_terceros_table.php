<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('terceros', function (Blueprint $table) {
            $table->id();
            $table->string('tipo_documento', 15);
            $table->string('numero_documento', 30);
            $table->string('nombre', 150);
            $table->string('tipo')->default('otro');
            $table->string('celular', 30)->nullable();
            $table->string('correo', 120)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('ultima_actualizacion')->nullable();
            $table->softDeletes('borrado_en');
            $table->unique(['tipo_documento', 'numero_documento']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('terceros');
    }
};
