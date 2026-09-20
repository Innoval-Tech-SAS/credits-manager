<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Ver docblock de `2026_09_08_030000_migrar_montos_de_creditos_a_centavos`. */
return new class extends Migration
{
    private const COLUMNAS = ['total_debito', 'total_credito'];

    public function up(): void
    {
        Schema::table('comprobantes', function (Blueprint $table) {
            foreach (self::COLUMNAS as $columna) {
                $table->bigInteger("{$columna}_centavos")->default(0)->after($columna);
            }
        });

        DB::statement('
            UPDATE comprobantes SET
                total_debito_centavos = ROUND(total_debito * 100),
                total_credito_centavos = ROUND(total_credito * 100)
        ');

        Schema::table('comprobantes', function (Blueprint $table) {
            $table->dropColumn(self::COLUMNAS);
        });

        Schema::table('comprobantes', function (Blueprint $table) {
            foreach (self::COLUMNAS as $columna) {
                $table->renameColumn("{$columna}_centavos", $columna);
            }
        });
    }

    public function down(): void
    {
        Schema::table('comprobantes', function (Blueprint $table) {
            foreach (self::COLUMNAS as $columna) {
                $table->decimal("{$columna}_decimal", 14, 2)->default(0)->after($columna);
            }
        });

        DB::statement('
            UPDATE comprobantes SET
                total_debito_decimal = total_debito / 100,
                total_credito_decimal = total_credito / 100
        ');

        Schema::table('comprobantes', function (Blueprint $table) {
            $table->dropColumn(self::COLUMNAS);
        });

        Schema::table('comprobantes', function (Blueprint $table) {
            foreach (self::COLUMNAS as $columna) {
                $table->renameColumn("{$columna}_decimal", $columna);
            }
        });
    }
};
