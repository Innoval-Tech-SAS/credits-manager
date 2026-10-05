<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Desglose del pago en capital e interés, para medir el recaudo de las metas con `base_recaudo`.
 * Nulo en los pagos anteriores: no se puede reconstruir con certeza el reparto de un pago ya aplicado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->bigInteger('valor_capital')->nullable()->after('valor_pago');
            $table->bigInteger('valor_interes')->nullable()->after('valor_capital');
        });
    }

    public function down(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->dropColumn(['valor_capital', 'valor_interes']);
        });
    }
};
