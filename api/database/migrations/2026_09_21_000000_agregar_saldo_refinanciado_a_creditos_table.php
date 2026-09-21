<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Solo las refinanciaciones lo llevan: el saldo de capital pendiente del crédito original
 * que se toma como base al solicitarla (centavos). `valor_prestamo` = este saldo + el nuevo
 * valor a prestar, que no se guarda porque se deriva restando. Es una foto, no un derivado
 * vivo: el original sigue recibiendo pagos hasta que se desembolsa la refinanciación, y ahí
 * se exige que su saldo siga siendo este. Nulo en los créditos nuevos y en refinanciaciones
 * anteriores a esta columna.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creditos', function (Blueprint $table) {
            $table->bigInteger('saldo_refinanciado')->nullable()->after('valor_prestamo');
        });
    }

    public function down(): void
    {
        Schema::table('creditos', function (Blueprint $table) {
            $table->dropColumn('saldo_refinanciado');
        });
    }
};
