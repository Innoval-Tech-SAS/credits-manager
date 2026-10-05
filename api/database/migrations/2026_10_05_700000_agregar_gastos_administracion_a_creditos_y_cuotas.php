<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Otros gastos" (gastos de administración): porcentaje sobre el capital inicial del
 * crédito o refinanciación, cobrado igual en cada cuota. El porcentaje vive en el crédito
 * (tasa, por eso decimal); el valor por cuota es dinero en centavos, fuera de `valor`,
 * que sigue siendo capital + interés del plan de amortización. Default 0: los créditos
 * existentes no cambian.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creditos', function (Blueprint $table) {
            $table->decimal('gastos_administracion_pct', 6, 3)->default(0)->after('tasa_interes');
        });

        Schema::table('cuotas', function (Blueprint $table) {
            $table->bigInteger('valor_otros_gastos')->default(0)->after('valor_interes');
        });
    }

    public function down(): void
    {
        Schema::table('cuotas', function (Blueprint $table) {
            $table->dropColumn('valor_otros_gastos');
        });

        Schema::table('creditos', function (Blueprint $table) {
            $table->dropColumn('gastos_administracion_pct');
        });
    }
};
