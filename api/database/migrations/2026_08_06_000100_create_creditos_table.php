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
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->string('tipo_credito')->default('nuevo');
            $table->foreignId('credito_origen_id')->nullable()->constrained('creditos')->nullOnDelete();
            $table->decimal('valor_prestamo', 14, 2);
            $table->decimal('tasa_interes', 6, 3);
            $table->integer('plazo');
            $table->string('tipo_pago')->default('mensual');
            $table->decimal('valor_cuota', 14, 2);
            $table->decimal('valor_total', 14, 2);
            $table->date('fecha_desembolso');
            $table->string('estado')->default('activo');
            $table->foreignId('creado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('ultima_actualizacion')->nullable();
            $table->softDeletes('borrado_en');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creditos');
    }
};
