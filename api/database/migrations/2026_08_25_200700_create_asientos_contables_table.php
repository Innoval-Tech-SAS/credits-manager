<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asientos_contables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comprobante_id')->constrained('comprobantes')->cascadeOnDelete();
            $table->foreignId('cuenta_id')->constrained('plan_cuentas');
            $table->foreignId('tercero_id')->nullable()->constrained('terceros')->nullOnDelete();
            $table->foreignId('centro_costo_id')->nullable()->constrained('centros_costo')->nullOnDelete();
            $table->decimal('debito', 14, 2)->default(0);
            $table->decimal('credito', 14, 2)->default(0);
            $table->string('descripcion', 255)->nullable();
            $table->unsignedInteger('orden')->default(0);
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('ultima_actualizacion')->nullable();
            $table->softDeletes('borrado_en');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asientos_contables');
    }
};
