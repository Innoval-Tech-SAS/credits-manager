<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('creditos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes')->restrictOnDelete();
            $table->string('codigo', 40)->nullable();
            $table->string('tipo_credito', 20)->default('nuevo');
            $table->foreignId('credito_origen_id')->nullable()->constrained('creditos')->nullOnDelete();
            $table->bigInteger('valor_prestamo');
            $table->bigInteger('saldo_refinanciado')->nullable();
            $table->decimal('tasa_interes', 6, 3);
            $table->decimal('gastos_administracion_pct', 6, 3)->default(0);
            $table->integer('plazo');
            $table->string('tipo_pago', 20)->default('mensual');
            $table->bigInteger('valor_cuota');
            $table->bigInteger('valor_total');
            $table->date('fecha_desembolso');
            $table->timestamp('desembolsado_en')->nullable();
            $table->string('estado', 20)->default('solicitado');
            $table->foreignId('creado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('ultima_actualizacion')->nullable();
            $table->softDeletes('borrado_en');

            $table->unique(['empresa_id', 'codigo']);
            $table->unique(['empresa_id', 'id']);
            $table->index(['empresa_id', 'fecha_desembolso'], 'creditos_empresa_fecha_desembolso_index');
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creditos');
    }
};
