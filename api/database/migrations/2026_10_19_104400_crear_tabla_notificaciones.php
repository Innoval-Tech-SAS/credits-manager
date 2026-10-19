<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notificaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('usuario_id')->constrained('usuarios');

            $table->string('tipo', 40);
            $table->string('titulo', 120);
            $table->string('mensaje', 500);
            $table->json('datos')->nullable();
            $table->timestamp('leida_en')->nullable();

            $table->timestamp('creado_en')->nullable();
            $table->timestamp('ultima_actualizacion')->nullable();
            $table->softDeletes('borrado_en');

            $table->index(['empresa_id', 'usuario_id', 'creado_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notificaciones');
    }
};
