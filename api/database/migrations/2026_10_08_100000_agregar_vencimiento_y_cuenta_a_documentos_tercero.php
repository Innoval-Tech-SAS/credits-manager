<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Columnas que pide la cartera por tercero (DDA): fecha de vencimiento (para mora/edades, se calcula "vencido" en
 * el query, no se persiste) y la cuenta PUC del documento (un tercero puede tener saldo en más de una cuenta,
 * p. ej. 2205 y 2335). `plan_cuenta_id` es de bajo riesgo (solo descriptivo/filtro, no mueve dinero): FK simple sin
 * scope compuesto de empresa, validada por el Service al escribir, igual que `gastos.tercero_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documentos_tercero', function (Blueprint $table) {
            $table->date('fecha_vencimiento')->nullable()->after('fecha');
            $table->foreignId('plan_cuenta_id')->nullable()->after('tipo_documento')->constrained('plan_cuentas')->nullOnDelete();

            $table->index(['empresa_id', 'naturaleza', 'estado']);
            // Para la FK compuesta que Facturación usará desde `documentos_facturacion.documento_tercero_id`.
            $table->unique(['empresa_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::table('documentos_tercero', function (Blueprint $table) {
            // Restaura el soporte de la FK de empresa_id (preexistente): sin esto, MySQL se niega a
            // borrar los dos índices de abajo porque son los únicos que la respaldan tras agregarlos.
            $table->index('empresa_id');
            $table->dropUnique(['empresa_id', 'id']);
            $table->dropIndex(['empresa_id', 'naturaleza', 'estado']);
            $table->dropConstrainedForeignId('plan_cuenta_id');
            $table->dropColumn('fecha_vencimiento');
        });
    }
};
