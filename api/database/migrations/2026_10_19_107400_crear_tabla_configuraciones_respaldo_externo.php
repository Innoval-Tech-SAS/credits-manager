<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fila única (sembrada por `ConfiguracionRespaldoExternoSeeder`, nunca creada ni borrada desde la
 * aplicación): configuración de la copia externa opcional del respaldo a un disco S3. Sin
 * `empresa_id`, igual que `tareas_programadas`: es infraestructura de la instancia completa, no un
 * dato de negocio de una empresa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuraciones_respaldo_externo', function (Blueprint $table) {
            $table->id();
            $table->boolean('activa')->default(false);
            $table->string('bucket', 190)->nullable();
            $table->string('region', 60)->nullable();
            $table->string('endpoint', 255)->nullable();
            $table->boolean('uso_path_style')->default(false);
            $table->string('clave_acceso', 190)->nullable();
            // Cifrada con APP_KEY (cast `encrypted`). Nunca sale por la API.
            $table->text('clave_secreta')->nullable();
            $table->foreignId('actualizado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('ultima_actualizacion')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuraciones_respaldo_externo');
    }
};
