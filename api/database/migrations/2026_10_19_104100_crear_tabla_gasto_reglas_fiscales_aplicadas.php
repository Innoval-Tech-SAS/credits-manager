<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Sin `empresa_id` propio: se segrega vía `gasto_id` → `gastos.empresa_id`. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gasto_reglas_fiscales_aplicadas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gasto_id')->constrained('gastos')->cascadeOnDelete();
            $table->string('tipo_retencion', 20);
            $table->boolean('incluida');
            $table->string('regla', 60);
            $table->decimal('tarifa_aplicada', 6, 3)->nullable();
            $table->text('detalle')->nullable();
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('ultima_actualizacion')->nullable();
            $table->softDeletes('borrado_en');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gasto_reglas_fiscales_aplicadas');
    }
};
