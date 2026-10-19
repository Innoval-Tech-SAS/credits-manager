<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** `evento` está respaldado por el backed enum `App\Contabilidad\Enums\EventoContable`, no es texto libre. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reglas_contabilizacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('evento', 60);
            $table->foreignId('cuenta_debito_id')->constrained('plan_cuentas');
            $table->foreignId('cuenta_credito_id')->constrained('plan_cuentas');
            $table->foreignId('tipo_comprobante_id')->constrained('tipos_comprobante');
            $table->boolean('activo')->default(true);
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('ultima_actualizacion')->nullable();
            $table->softDeletes('borrado_en');

            $table->unique(['empresa_id', 'evento']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reglas_contabilizacion');
    }
};
