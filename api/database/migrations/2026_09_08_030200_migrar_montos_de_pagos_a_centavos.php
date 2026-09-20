<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Ver docblock de `2026_09_08_030000_migrar_montos_de_creditos_a_centavos`. */
return new class extends Migration
{
    private const COLUMNAS = ['valor_pago'];

    public function up(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->bigInteger('valor_pago_centavos')->default(0)->after('valor_pago');
        });

        DB::statement('UPDATE pagos SET valor_pago_centavos = ROUND(valor_pago * 100)');

        Schema::table('pagos', function (Blueprint $table) {
            $table->dropColumn(self::COLUMNAS);
        });

        Schema::table('pagos', function (Blueprint $table) {
            $table->renameColumn('valor_pago_centavos', 'valor_pago');
        });
    }

    public function down(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->decimal('valor_pago_decimal', 14, 2)->default(0)->after('valor_pago');
        });

        DB::statement('UPDATE pagos SET valor_pago_decimal = valor_pago / 100');

        Schema::table('pagos', function (Blueprint $table) {
            $table->dropColumn(self::COLUMNAS);
        });

        Schema::table('pagos', function (Blueprint $table) {
            $table->renameColumn('valor_pago_decimal', 'valor_pago');
        });
    }
};
