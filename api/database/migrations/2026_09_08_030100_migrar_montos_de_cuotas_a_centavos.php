<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Ver docblock de `2026_09_08_030000_migrar_montos_de_creditos_a_centavos`. */
return new class extends Migration
{
    private const COLUMNAS = ['valor', 'valor_interes', 'valor_capital', 'saldo_capital', 'valor_pagado'];

    public function up(): void
    {
        Schema::table('cuotas', function (Blueprint $table) {
            foreach (self::COLUMNAS as $columna) {
                $table->bigInteger("{$columna}_centavos")->default(0)->after($columna);
            }
        });

        DB::statement('
            UPDATE cuotas SET
                valor_centavos = ROUND(valor * 100),
                valor_interes_centavos = ROUND(valor_interes * 100),
                valor_capital_centavos = ROUND(valor_capital * 100),
                saldo_capital_centavos = ROUND(saldo_capital * 100),
                valor_pagado_centavos = ROUND(valor_pagado * 100)
        ');

        Schema::table('cuotas', function (Blueprint $table) {
            $table->dropColumn(self::COLUMNAS);
        });

        Schema::table('cuotas', function (Blueprint $table) {
            foreach (self::COLUMNAS as $columna) {
                $table->renameColumn("{$columna}_centavos", $columna);
            }
        });
    }

    public function down(): void
    {
        Schema::table('cuotas', function (Blueprint $table) {
            foreach (self::COLUMNAS as $columna) {
                $table->decimal("{$columna}_decimal", 14, 2)->default(0)->after($columna);
            }
        });

        DB::statement('
            UPDATE cuotas SET
                valor_decimal = valor / 100,
                valor_interes_decimal = valor_interes / 100,
                valor_capital_decimal = valor_capital / 100,
                saldo_capital_decimal = saldo_capital / 100,
                valor_pagado_decimal = valor_pagado / 100
        ');

        Schema::table('cuotas', function (Blueprint $table) {
            $table->dropColumn(self::COLUMNAS);
        });

        Schema::table('cuotas', function (Blueprint $table) {
            foreach (self::COLUMNAS as $columna) {
                $table->renameColumn("{$columna}_decimal", $columna);
            }
        });
    }
};
