<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enlaza cada factura con su `documento_tercero` (cuenta por cobrar), para que la cartera por tercero sea un solo
 * motor para CxP y CxC, como pide DDA. El saldo/situación de la factura sigue derivándose de `cobros_facturacion` y
 * notas (ya probado en Fase 5): `documento_tercero_id` es un puntero adicional que el Service mantiene cruzado en
 * la misma transacción de emisión/nota/cobro, no un segundo cálculo independiente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documentos_facturacion', function (Blueprint $table) {
            $table->date('fecha_vencimiento')->nullable()->after('fecha');
            $table->unsignedBigInteger('documento_tercero_id')->nullable()->after('comprobante_id');
            $table->foreign(['empresa_id', 'documento_tercero_id'])->references(['empresa_id', 'id'])->on('documentos_tercero');
        });
    }

    public function down(): void
    {
        Schema::table('documentos_facturacion', function (Blueprint $table) {
            $table->dropForeign(['empresa_id', 'documento_tercero_id']);
            $table->dropColumn(['documento_tercero_id', 'fecha_vencimiento']);
        });
    }
};
