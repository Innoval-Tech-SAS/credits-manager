<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Una fila por empresa y tipo de concepto de factura (capital, interés corriente, interés de mora, otros gastos):
 * a diferencia de `parametrizaciones_tipo_gasto` (catálogo abierto, con vigencia por fechas), los 4 conceptos son
 * fijos y la fila se edita en sitio. Mismo molde que `parametrizaciones_tipo_gasto` para IVA/retenciones.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parametrizaciones_concepto_facturacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('tipo_concepto', 30);

            $table->decimal('tarifa_iva', 6, 3)->nullable();
            $table->foreignId('cuenta_iva_id')->nullable()->constrained('plan_cuentas');

            $table->boolean('aplica_retefuente')->default(false);
            $table->unsignedInteger('base_minima_uvt')->nullable();
            $table->decimal('tarifa_retefuente', 6, 3)->nullable();
            // Nombre de constraint explícito: el autogenerado (tabla + columna + "_foreign") supera los 64 caracteres de MySQL.
            $table->foreignId('cuenta_retefuente_id')->nullable()->constrained('plan_cuentas', 'id', 'pcf_cuenta_retefuente_id_foreign');

            $table->boolean('aplica_reteica')->default(false);
            $table->foreignId('cuenta_reteica_id')->nullable()->constrained('plan_cuentas');

            $table->boolean('aplica_reteiva')->default(false);
            $table->decimal('tarifa_reteiva', 6, 3)->nullable();
            $table->foreignId('cuenta_reteiva_id')->nullable()->constrained('plan_cuentas');

            $table->foreignId('actualizado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('ultima_actualizacion')->nullable();
            $table->softDeletes('borrado_en');

            // Nombre de índice explícito: el autogenerado supera los 64 caracteres de MySQL.
            $table->unique(['empresa_id', 'tipo_concepto'], 'pcf_empresa_tipo_concepto_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parametrizaciones_concepto_facturacion');
    }
};
