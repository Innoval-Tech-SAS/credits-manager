<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `cuota_id` + `tipo_concepto` trazan una línea de factura a su cuota del plan de pagos (igual que `pago_detalle`
 * y `envios_correo` ya apuntan a `cuotas`, sin FK compuesta por empresa porque `cuotas` no tiene `empresa_id`
 * propio — el aislamiento ya lo garantiza validar `credito_id` contra la empresa antes de aceptar `cuota_id`).
 * `valor_iva`/`valor_retefuente`/`valor_reteica`/`valor_reteiva` quedan congelados al guardar la línea: un
 * documento emitido no se recalcula si cambia la parametrización después.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lineas_documento_facturacion', function (Blueprint $table) {
            $table->foreignId('cuota_id')->nullable()->after('linea_origen_id')->constrained('cuotas')->nullOnDelete();
            $table->string('tipo_concepto', 30)->nullable()->after('cuota_id');

            $table->bigInteger('valor_iva')->default(0)->after('valor');
            $table->bigInteger('valor_retefuente')->default(0)->after('valor_iva');
            $table->bigInteger('valor_reteica')->default(0)->after('valor_retefuente');
            $table->bigInteger('valor_reteiva')->default(0)->after('valor_reteica');

            $table->index(['empresa_id', 'cuota_id', 'tipo_concepto'], 'lineas_fac_cuota_concepto_index');
        });
    }

    public function down(): void
    {
        Schema::table('lineas_documento_facturacion', function (Blueprint $table) {
            $table->dropIndex('lineas_fac_cuota_concepto_index');
            $table->dropConstrainedForeignId('cuota_id');
            $table->dropColumn(['tipo_concepto', 'valor_iva', 'valor_retefuente', 'valor_reteica', 'valor_reteiva']);
        });
    }
};
