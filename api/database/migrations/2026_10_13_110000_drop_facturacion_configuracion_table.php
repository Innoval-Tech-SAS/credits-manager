<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** La nota débito no tiene tope configurable: solo exige soporte. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('facturacion_configuracion');
    }

    public function down(): void
    {
        Schema::create('facturacion_configuracion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('clave', 60);
            $table->string('valor', 60);
            $table->foreignId('actualizado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('ultima_actualizacion')->nullable();
            $table->softDeletes('borrado_en');

            $table->unique(['empresa_id', 'clave']);
        });
    }
};
