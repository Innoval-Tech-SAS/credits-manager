<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Flags declarativos por cuenta (observaciones DDA "Parametrización y trazabilidad"): qué exige cada cuenta al
 * usarse en un comprobante, en vez de que cada Service adivine la regla por el `tipo` de la cuenta. Por ahora solo
 * `exige_centro_costo` tiene un punto de aplicación (reemplaza la regla hardcodeada de `ComprobanteService`); los
 * otros tres quedan declarados para que los flujos de CxC/CxP (causación, egreso, cobro) los lean cuando se
 * implementen — ver roadmap.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plan_cuentas', function (Blueprint $table) {
            $table->boolean('exige_tercero')->default(false)->after('permite_movimiento');
            $table->boolean('exige_documento_cruce')->default(false)->after('exige_tercero');
            $table->boolean('exige_vencimiento')->default(false)->after('exige_documento_cruce');
            $table->boolean('exige_centro_costo')->default(false)->after('exige_vencimiento');
        });
    }

    public function down(): void
    {
        Schema::table('plan_cuentas', function (Blueprint $table) {
            $table->dropColumn(['exige_tercero', 'exige_documento_cruce', 'exige_vencimiento', 'exige_centro_costo']);
        });
    }
};
