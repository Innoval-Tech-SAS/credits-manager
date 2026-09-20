<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('tipo_documento');
            $table->string('numero_documento')->unique();
            $table->string('telefono');
            $table->string('correo');
            $table->string('empleador');
            $table->boolean('activo')->default(true);
            $table->foreignId('creado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('ultima_actualizacion')->nullable();
            $table->softDeletes('borrado_en');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
