<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trazabilidad entre una línea de factura de interés ya pagado y el (o los) `pago_detalle` que lo cubrieron —
 * mismo patrón que `aplicaciones_documento` (sin `empresa_id` propio: solo la crea `DocumentoFacturacionService`
 * a partir de modelos ya resueltos por sus propias consultas con scope de empresa, nunca de un id que llegue del
 * cliente). Una cuota puede haber pagado su interés en más de un pago (abonos parciales sucesivos), de ahí que sea
 * una tabla de relación y no una columna en `lineas_documento_facturacion`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aplicaciones_interes_facturado', function (Blueprint $table) {
            $table->id();
            $table->foreignId('linea_documento_facturacion_id')->constrained('lineas_documento_facturacion', 'id', 'aplic_interes_fac_linea_foreign')->cascadeOnDelete();
            $table->foreignId('pago_detalle_id')->constrained('pago_detalle', 'id', 'aplic_interes_fac_pago_detalle_foreign')->cascadeOnDelete();
            // Centavos, con DineroCast en el Model: cuánto del interés de ese pago_detalle quedó en esta línea.
            $table->bigInteger('valor');
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('ultima_actualizacion')->nullable();
            $table->softDeletes('borrado_en');

            $table->unique(['linea_documento_facturacion_id', 'pago_detalle_id'], 'aplic_interes_fac_linea_pago_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aplicaciones_interes_facturado');
    }
};
