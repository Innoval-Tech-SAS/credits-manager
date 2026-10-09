<?php

use App\Contabilidad\Enums\TipoCuenta;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Antes de este flag, `ComprobanteService` exigía centro de costo a toda cuenta tipo Gasto/Costo. Se preserva ese
 * comportamiento para las cuentas ya creadas; de aquí en adelante es configurable por cuenta, no por tipo.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('plan_cuentas')
            ->whereIn('tipo', [TipoCuenta::Gasto->value, TipoCuenta::Costo->value])
            ->update(['exige_centro_costo' => true]);
    }

    public function down(): void
    {
        DB::table('plan_cuentas')
            ->whereIn('tipo', [TipoCuenta::Gasto->value, TipoCuenta::Costo->value])
            ->update(['exige_centro_costo' => false]);
    }
};
