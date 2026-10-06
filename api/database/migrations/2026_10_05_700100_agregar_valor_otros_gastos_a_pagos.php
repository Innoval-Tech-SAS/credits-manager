<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Parte del pago que corresponde a otros gastos de administración. Cero en los pagos anteriores a esta columna. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->bigInteger('valor_otros_gastos')->default(0)->after('valor_interes');
        });
    }

    public function down(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->dropColumn('valor_otros_gastos');
        });
    }
};
