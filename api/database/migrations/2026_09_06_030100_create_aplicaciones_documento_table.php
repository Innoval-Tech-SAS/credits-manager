<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aplicaciones_documento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('documento_tercero_id')->constrained('documentos_tercero')->cascadeOnDelete();
            $table->string('modulo_aplicacion', 60);
            $table->unsignedBigInteger('aplicacion_id')->nullable();
            $table->decimal('valor_aplicado', 14, 2);
            $table->date('fecha');
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('ultima_actualizacion')->nullable();
            $table->softDeletes('borrado_en');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aplicaciones_documento');
    }
};
