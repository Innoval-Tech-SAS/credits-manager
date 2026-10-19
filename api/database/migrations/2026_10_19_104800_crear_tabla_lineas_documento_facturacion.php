<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Líneas de los documentos de facturación. La nota crédito apunta a la línea de la factura que corrige
 * (`linea_origen_id`), así la base impide cruzar líneas de otra empresa y queda la trazabilidad por concepto.
 * `cuota_id` + `tipo_concepto` trazan la línea a su cuota del plan de pagos (sin FK compuesta por empresa
 * porque `cuotas` no tiene `empresa_id` propio — el aislamiento ya lo garantiza validar `credito_id` contra la
 * empresa antes de aceptar `cuota_id`). Los valores de impuesto quedan congelados al guardar la línea: un
 * documento emitido no se recalcula si cambia la parametrización después. `tasa_mora`/`dias_mora`/`base_mora`
 * solo aplican a la línea de una nota débito por intereses de mora.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lineas_documento_facturacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->unsignedBigInteger('documento_id');
            $table->unsignedBigInteger('linea_origen_id')->nullable();
            $table->foreignId('cuota_id')->nullable()->constrained('cuotas')->nullOnDelete();
            $table->string('tipo_concepto', 30)->nullable();
            $table->unsignedSmallInteger('orden');
            $table->string('concepto', 60);
            $table->string('descripcion', 255);
            // Centavos, con DineroCast en el Model.
            $table->bigInteger('valor');
            $table->bigInteger('valor_iva')->default(0);
            $table->bigInteger('valor_retefuente')->default(0);
            $table->bigInteger('valor_reteica')->default(0);
            $table->bigInteger('valor_reteiva')->default(0);
            $table->decimal('tasa_mora', 8, 4)->nullable();
            $table->unsignedSmallInteger('dias_mora')->nullable();
            // Centavos, con DineroCast en el Model.
            $table->bigInteger('base_mora')->nullable();
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('ultima_actualizacion')->nullable();
            $table->softDeletes('borrado_en');

            $table->unique(['empresa_id', 'id']);
            // Nombres explícitos: MySQL limita los identificadores a 64 caracteres.
            $table->unique(['empresa_id', 'documento_id', 'orden'], 'lineas_fac_orden_unique');
            $table->index(['empresa_id', 'linea_origen_id'], 'lineas_fac_origen_index');
            $table->index(['empresa_id', 'cuota_id', 'tipo_concepto'], 'lineas_fac_cuota_concepto_index');

            $table->foreign(['empresa_id', 'documento_id'])->references(['empresa_id', 'id'])->on('documentos_facturacion');
            $table->foreign(['empresa_id', 'linea_origen_id'])->references(['empresa_id', 'id'])->on('lineas_documento_facturacion');
        });

        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE lineas_documento_facturacion ADD CONSTRAINT lineas_documento_facturacion_valor_check CHECK (valor > 0)');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE lineas_documento_facturacion DROP CHECK lineas_documento_facturacion_valor_check');
        }

        Schema::dropIfExists('lineas_documento_facturacion');
    }
};
