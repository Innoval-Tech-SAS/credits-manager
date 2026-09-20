<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tarifas_reteica_municipio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('municipio_id')->constrained('municipios');
            $table->foreignId('actividad_economica_id')->constrained('actividades_economicas');
            $table->date('fecha_desde');
            $table->date('fecha_hasta')->nullable();
            $table->decimal('tarifa', 6, 3);
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('ultima_actualizacion')->nullable();
            $table->softDeletes('borrado_en');

            $table->index(['municipio_id', 'actividad_economica_id', 'fecha_desde'], 'tarifas_reteica_municipio_vigencia_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tarifas_reteica_municipio');
    }
};
