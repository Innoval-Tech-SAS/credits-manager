<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Sin `empresa_id` propio: se scope vía `pago_id` (ver `EmpresaScope` en el Model). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pago_detalle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pago_id')->constrained('pagos')->cascadeOnDelete();
            $table->foreignId('cuota_id')->constrained('cuotas')->restrictOnDelete();
            $table->bigInteger('valor_aplicado');
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('ultima_actualizacion')->nullable();
            $table->softDeletes('borrado_en');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pago_detalle');
    }
};
