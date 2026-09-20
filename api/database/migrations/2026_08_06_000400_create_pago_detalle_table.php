<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pago_detalle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pago_id')->constrained('pagos')->cascadeOnDelete();
            $table->foreignId('cuota_id')->constrained('cuotas');
            $table->decimal('valor_aplicado', 14, 2);
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
