<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `fecha_desembolso` es un `date` (sin hora) y se usa para el cronograma de cuotas —
 * no sirve para calcular una ventana de "24 horas desde el desembolso". Esta columna
 * registra el instante exacto en que `CreditoService::desembolsar()` hizo la
 * transición, para esa validación.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creditos', function (Blueprint $table) {
            $table->timestamp('desembolsado_en')->nullable()->after('fecha_desembolso');
        });
    }

    public function down(): void
    {
        Schema::table('creditos', function (Blueprint $table) {
            $table->dropColumn('desembolsado_en');
        });
    }
};
