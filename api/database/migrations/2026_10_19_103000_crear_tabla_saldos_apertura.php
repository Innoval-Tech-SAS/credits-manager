<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saldos_apertura', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('cuenta_id')->constrained('plan_cuentas');
            $table->foreignId('tercero_id')->nullable()->constrained('terceros')->nullOnDelete();
            $table->date('fecha_corte');
            $table->bigInteger('debito')->default(0);
            $table->bigInteger('credito')->default(0);
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('ultima_actualizacion')->nullable();
            $table->softDeletes('borrado_en');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saldos_apertura');
    }
};
