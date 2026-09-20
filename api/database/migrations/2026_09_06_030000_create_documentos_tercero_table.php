<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentos_tercero', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('tercero_id')->constrained('terceros');
            $table->string('naturaleza', 20);
            $table->string('tipo_documento', 60);
            $table->string('numero_documento', 60);
            $table->date('fecha');
            $table->string('concepto', 255);
            $table->decimal('valor_original', 14, 2);
            $table->decimal('saldo', 14, 2);
            $table->string('estado', 20)->default('pendiente');
            $table->string('modulo_origen', 60)->nullable();
            $table->unsignedBigInteger('origen_id')->nullable();
            $table->foreignId('documento_original_id')->nullable()->constrained('documentos_tercero')->nullOnDelete();
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('ultima_actualizacion')->nullable();
            $table->softDeletes('borrado_en');

            $table->index(['tercero_id', 'naturaleza', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentos_tercero');
    }
};
