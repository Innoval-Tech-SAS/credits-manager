<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Un recibo nace de un pago de crédito o de un cobro de facturación, nunca de los dos (`origen_tipo` +
 * CHECK). `cobro_facturacion_id` apunta a `cobros_facturacion` (módulo Facturación, creada más adelante): FK
 * compuesta por empresa igual que `pago_id`, declarada ya con esa forma. `comprobante_id` apunta a `comprobantes`
 * (módulo Contabilidad, también posterior): FK simple, no compuesta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recibos_caja', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->unsignedBigInteger('pago_id')->nullable();
            $table->string('origen_tipo', 20)->default('pago');
            $table->unsignedBigInteger('cobro_facturacion_id')->nullable();
            $table->foreignId('comprobante_id')->nullable()->constrained('comprobantes')->restrictOnDelete();
            $table->unsignedInteger('consecutivo');
            // Prefijo y consecutivo tal como se imprimieron: no cambian aunque después se cambie el prefijo de la empresa.
            $table->string('numero', 30);
            $table->string('estado', 20)->default('emitido');
            $table->foreignId('emitido_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamp('anulado_en')->nullable();
            $table->foreignId('anulado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->string('motivo_anulacion', 500)->nullable();
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('ultima_actualizacion')->nullable();
            $table->softDeletes('borrado_en');

            $table->unique(['empresa_id', 'pago_id']);
            $table->unique(['empresa_id', 'cobro_facturacion_id']);
            $table->unique(['empresa_id', 'consecutivo']);
            $table->index(['empresa_id', 'estado']);

            $table->foreign(['empresa_id', 'pago_id'])->references(['empresa_id', 'id'])->on('pagos');
            $table->foreign(['empresa_id', 'cobro_facturacion_id'])->references(['empresa_id', 'id'])->on('cobros_facturacion');
        });

        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement(
            'ALTER TABLE recibos_caja ADD CONSTRAINT recibos_caja_origen_check CHECK ('.
            "(origen_tipo = 'pago' AND pago_id IS NOT NULL AND cobro_facturacion_id IS NULL) ".
            "OR (origen_tipo = 'cobro_facturacion' AND cobro_facturacion_id IS NOT NULL AND pago_id IS NULL)".
            ')',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('recibos_caja');
    }
};
