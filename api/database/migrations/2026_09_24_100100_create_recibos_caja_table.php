<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Respalda la FK compuesta de recibos_caja: la base rechaza un recibo de una empresa sobre el pago de otra.
        Schema::table('pagos', function (Blueprint $table) {
            $table->unique(['empresa_id', 'id']);
        });

        Schema::create('recibos_caja', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->unsignedBigInteger('pago_id');
            $table->foreignId('comprobante_id')->nullable()->constrained('comprobantes');
            $table->unsignedInteger('consecutivo');
            // Prefijo y consecutivo tal como se imprimieron: no cambian aunque después se cambie el prefijo de la empresa.
            $table->string('numero', 30);
            $table->string('estado', 20)->default('emitido');
            $table->foreignId('emitido_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('ultima_actualizacion')->nullable();
            $table->softDeletes('borrado_en');

            $table->unique('pago_id');
            $table->unique(['empresa_id', 'consecutivo']);
            $table->index(['empresa_id', 'estado']);
            $table->foreign(['empresa_id', 'pago_id'])->references(['empresa_id', 'id'])->on('pagos');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recibos_caja');

        Schema::table('pagos', function (Blueprint $table) {
            $table->dropUnique(['empresa_id', 'id']);
        });
    }
};
