<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migra las columnas monetarias de `decimal(14,2)` a `bigint` (centavos enteros) para
 * eliminar el antipatrón "dinero como float/decimal" (ver App\Core\Dinero\Dinero y
 * DineroCast). `MODIFY COLUMN decimal->bigint` directo trunca en vez de multiplicar
 * por 100, así que el paso es: 1) columnas nuevas *_centavos con default 0 (para que
 * el ALTER no falle en tablas con filas existentes), 2) backfill con `ROUND(col * 100)`
 * vía SQL crudo (incluye soft-deleted, no pasa por el scope de Eloquent), 3) drop de
 * las columnas decimal, 4) rename a los nombres originales. BIGINT firmado (no
 * UNSIGNED): una resta intermedia como `SUM(valor - valor_pagado)` puede ser
 * transitoriamente negativa.
 */
return new class extends Migration
{
    private const COLUMNAS = ['valor_prestamo', 'valor_cuota', 'valor_total'];

    public function up(): void
    {
        Schema::table('creditos', function (Blueprint $table) {
            foreach (self::COLUMNAS as $columna) {
                $table->bigInteger("{$columna}_centavos")->default(0)->after($columna);
            }
        });

        DB::statement('
            UPDATE creditos SET
                valor_prestamo_centavos = ROUND(valor_prestamo * 100),
                valor_cuota_centavos = ROUND(valor_cuota * 100),
                valor_total_centavos = ROUND(valor_total * 100)
        ');

        Schema::table('creditos', function (Blueprint $table) {
            $table->dropColumn(self::COLUMNAS);
        });

        Schema::table('creditos', function (Blueprint $table) {
            foreach (self::COLUMNAS as $columna) {
                $table->renameColumn("{$columna}_centavos", $columna);
            }
        });
    }

    public function down(): void
    {
        Schema::table('creditos', function (Blueprint $table) {
            foreach (self::COLUMNAS as $columna) {
                $table->decimal("{$columna}_decimal", 14, 2)->default(0)->after($columna);
            }
        });

        DB::statement('
            UPDATE creditos SET
                valor_prestamo_decimal = valor_prestamo / 100,
                valor_cuota_decimal = valor_cuota / 100,
                valor_total_decimal = valor_total / 100
        ');

        Schema::table('creditos', function (Blueprint $table) {
            $table->dropColumn(self::COLUMNAS);
        });

        Schema::table('creditos', function (Blueprint $table) {
            foreach (self::COLUMNAS as $columna) {
                $table->renameColumn("{$columna}_decimal", $columna);
            }
        });
    }
};
