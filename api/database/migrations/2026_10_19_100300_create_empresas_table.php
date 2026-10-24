<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `actividad_economica_id` no va aquí: `actividades_economicas` tiene `empresa_id` → `empresas`, así que esa FK
 * se agrega en una migración aparte una vez creada `actividades_economicas`, para no cerrar un ciclo de FK.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empresas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_empresa');
            $table->string('nit');
            $table->string('digito_verificacion', 1)->nullable();
            $table->string('direccion');
            $table->foreignId('municipio_id')->nullable()->constrained('municipios');
            $table->string('telefono');
            $table->string('correo');
            $table->string('logo_path')->nullable();
            $table->boolean('responsable_iva')->default(false);
            $table->boolean('autorretenedor')->default(false);
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('ultima_actualizacion')->nullable();
            $table->softDeletes('borrado_en');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empresas');
    }
};
