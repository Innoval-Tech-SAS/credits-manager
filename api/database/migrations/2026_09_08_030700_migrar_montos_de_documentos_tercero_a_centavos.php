<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Ver docblock de `2026_09_08_030000_migrar_montos_de_creditos_a_centavos`. */
return new class extends Migration
{
    private const COLUMNAS = ['valor_original', 'saldo'];

    public function up(): void
    {
        Schema::table('documentos_tercero', function (Blueprint $table) {
            foreach (self::COLUMNAS as $columna) {
                $table->bigInteger("{$columna}_centavos")->default(0)->after($columna);
            }
        });

        DB::statement('
            UPDATE documentos_tercero SET
                valor_original_centavos = ROUND(valor_original * 100),
                saldo_centavos = ROUND(saldo * 100)
        ');

        Schema::table('documentos_tercero', function (Blueprint $table) {
            $table->dropColumn(self::COLUMNAS);
        });

        Schema::table('documentos_tercero', function (Blueprint $table) {
            foreach (self::COLUMNAS as $columna) {
                $table->renameColumn("{$columna}_centavos", $columna);
            }
        });
    }

    public function down(): void
    {
        Schema::table('documentos_tercero', function (Blueprint $table) {
            foreach (self::COLUMNAS as $columna) {
                $table->decimal("{$columna}_decimal", 14, 2)->default(0)->after($columna);
            }
        });

        DB::statement('
            UPDATE documentos_tercero SET
                valor_original_decimal = valor_original / 100,
                saldo_decimal = saldo / 100
        ');

        Schema::table('documentos_tercero', function (Blueprint $table) {
            $table->dropColumn(self::COLUMNAS);
        });

        Schema::table('documentos_tercero', function (Blueprint $table) {
            foreach (self::COLUMNAS as $columna) {
                $table->renameColumn("{$columna}_decimal", $columna);
            }
        });
    }
};
