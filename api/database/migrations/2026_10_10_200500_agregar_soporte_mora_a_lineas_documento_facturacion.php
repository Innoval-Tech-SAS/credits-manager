<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Soporte del cálculo cuando una línea de nota débito es por intereses de mora: tasa, días y base sobre
 * la que se calculó. Nulo en toda línea de factura o de nota crédito, y en la nota débito cuyo motivo no es mora.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lineas_documento_facturacion', function (Blueprint $table) {
            $table->decimal('tasa_mora', 8, 4)->nullable()->after('valor');
            $table->unsignedSmallInteger('dias_mora')->nullable()->after('tasa_mora');
            // Centavos, con DineroCast en el Model.
            $table->bigInteger('base_mora')->nullable()->after('dias_mora');
        });
    }

    public function down(): void
    {
        Schema::table('lineas_documento_facturacion', function (Blueprint $table) {
            $table->dropColumn(['tasa_mora', 'dias_mora', 'base_mora']);
        });
    }
};
