<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `valor_capital`/`valor_interes` nulos en los pagos anteriores al desglose: no se puede reconstruir con certeza
 * el reparto de un pago ya aplicado. `fecha_pago` es `date` (fecha de negocio, no instante) — `creado_en` ya cubre
 * el instante real del registro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('credito_id')->constrained('creditos')->restrictOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes')->restrictOnDelete();
            $table->bigInteger('valor_pago');
            $table->bigInteger('valor_capital')->nullable();
            $table->bigInteger('valor_interes')->nullable();
            $table->bigInteger('valor_otros_gastos')->default(0);
            $table->date('fecha_pago');
            $table->string('forma_pago', 20)->default('efectivo');
            $table->string('observacion')->nullable();
            $table->foreignId('registrado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('ultima_actualizacion')->nullable();
            $table->softDeletes('borrado_en');

            $table->unique(['empresa_id', 'id']);
            $table->index(['empresa_id', 'fecha_pago'], 'pagos_empresa_fecha_pago_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
};
