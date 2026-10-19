<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** `unique(['empresa_id', 'id'])`: prerrequisito de las FK compuestas de facturación que apuntan a clientes. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('tercero_id')->unique()->constrained('terceros');
            $table->foreignId('asesor_id')->constrained('usuarios')->restrictOnDelete();
            $table->foreignId('empleador_id')->nullable()->constrained('empleadores')->nullOnDelete();
            $table->boolean('activo')->default(true);
            $table->foreignId('creado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('ultima_actualizacion')->nullable();
            $table->softDeletes('borrado_en');

            $table->unique(['empresa_id', 'id']);
            $table->index(['empresa_id', 'asesor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
