<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Ver docblock de `2026_09_08_030000_migrar_montos_de_creditos_a_centavos`. */
return new class extends Migration
{
    private const COLUMNAS = ['valor_bruto', 'valor_iva', 'valor_retefuente', 'valor_reteica', 'valor_reteiva', 'valor_neto'];

    public function up(): void
    {
        Schema::table('gastos', function (Blueprint $table) {
            foreach (self::COLUMNAS as $columna) {
                $table->bigInteger("{$columna}_centavos")->default(0)->after($columna);
            }
        });

        DB::statement('
            UPDATE gastos SET
                valor_bruto_centavos = ROUND(valor_bruto * 100),
                valor_iva_centavos = ROUND(valor_iva * 100),
                valor_retefuente_centavos = ROUND(valor_retefuente * 100),
                valor_reteica_centavos = ROUND(valor_reteica * 100),
                valor_reteiva_centavos = ROUND(valor_reteiva * 100),
                valor_neto_centavos = ROUND(valor_neto * 100)
        ');

        Schema::table('gastos', function (Blueprint $table) {
            $table->dropColumn(self::COLUMNAS);
        });

        Schema::table('gastos', function (Blueprint $table) {
            foreach (self::COLUMNAS as $columna) {
                $table->renameColumn("{$columna}_centavos", $columna);
            }
        });
    }

    public function down(): void
    {
        Schema::table('gastos', function (Blueprint $table) {
            foreach (self::COLUMNAS as $columna) {
                $table->decimal("{$columna}_decimal", 14, 2)->default(0)->after($columna);
            }
        });

        DB::statement('
            UPDATE gastos SET
                valor_bruto_decimal = valor_bruto / 100,
                valor_iva_decimal = valor_iva / 100,
                valor_retefuente_decimal = valor_retefuente / 100,
                valor_reteica_decimal = valor_reteica / 100,
                valor_reteiva_decimal = valor_reteiva / 100,
                valor_neto_decimal = valor_neto / 100
        ');

        Schema::table('gastos', function (Blueprint $table) {
            $table->dropColumn(self::COLUMNAS);
        });

        Schema::table('gastos', function (Blueprint $table) {
            foreach (self::COLUMNAS as $columna) {
                $table->renameColumn("{$columna}_decimal", $columna);
            }
        });
    }
};
