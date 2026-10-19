<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('importaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('usuario_id')->constrained('usuarios');

            $table->string('tipo', 30);
            $table->string('estado', 30)->default('pendiente');
            $table->string('archivo_original', 255);
            $table->string('archivo_ruta', 255)->nullable();

            $table->boolean('preferencia_crear_empleadores')->default(false);
            $table->boolean('preferencia_generar_asiento')->default(false);
            $table->foreignId('cuenta_contrapartida_id')->nullable()->constrained('plan_cuentas')->nullOnDelete();
            $table->foreignId('tipo_comprobante_id')->nullable()->constrained('tipos_comprobante')->nullOnDelete();

            $table->boolean('decision_crear_empleadores')->nullable();
            $table->boolean('decision_generar_asiento')->nullable();
            $table->foreignId('comprobante_id')->nullable()->constrained('comprobantes')->nullOnDelete();

            $table->unsignedInteger('total_filas')->default(0);
            $table->unsignedInteger('filas_aceptadas')->default(0);
            $table->unsignedInteger('filas_rechazadas')->default(0);
            $table->unsignedInteger('filas_pendientes')->default(0);
            $table->unsignedInteger('empleadores_pendientes')->default(0);

            $table->text('error')->nullable();
            $table->timestamp('iniciada_en')->nullable();
            $table->timestamp('validada_en')->nullable();
            $table->timestamp('completada_en')->nullable();

            $table->timestamp('creado_en')->nullable();
            $table->timestamp('ultima_actualizacion')->nullable();
            $table->softDeletes('borrado_en');

            $table->index(['empresa_id', 'estado']);
            $table->index(['empresa_id', 'creado_en']);
            $table->unique(['empresa_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('importaciones');
    }
};
