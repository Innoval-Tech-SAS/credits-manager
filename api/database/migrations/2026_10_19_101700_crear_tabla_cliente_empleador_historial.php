<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * No es de solo inserción: cambiar de empleador cierra el período anterior actualizando su `fecha_fin`
 * (ver `ClienteEmpleadorHistorial`, que extiende `BaseModel` normal, con soft delete).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cliente_empleador_historial', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->foreignId('empleador_id')->constrained('empleadores')->restrictOnDelete();
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('ultima_actualizacion')->nullable();
            $table->softDeletes('borrado_en');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cliente_empleador_historial');
    }
};
