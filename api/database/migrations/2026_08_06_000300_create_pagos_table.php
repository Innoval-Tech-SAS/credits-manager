<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credito_id')->constrained('creditos');
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->decimal('valor_pago', 14, 2);
            $table->timestamp('fecha_pago')->useCurrent();
            $table->string('forma_pago')->default('efectivo');
            $table->string('observacion')->nullable();
            $table->foreignId('registrado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('ultima_actualizacion')->nullable();
            $table->softDeletes('borrado_en');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
};
