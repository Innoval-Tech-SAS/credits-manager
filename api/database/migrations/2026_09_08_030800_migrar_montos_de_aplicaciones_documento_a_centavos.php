<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Ver docblock de `2026_09_08_030000_migrar_montos_de_creditos_a_centavos`. */
return new class extends Migration
{
    private const COLUMNAS = ['valor_aplicado'];

    public function up(): void
    {
        Schema::table('aplicaciones_documento', function (Blueprint $table) {
            $table->bigInteger('valor_aplicado_centavos')->default(0)->after('valor_aplicado');
        });

        DB::statement('UPDATE aplicaciones_documento SET valor_aplicado_centavos = ROUND(valor_aplicado * 100)');

        Schema::table('aplicaciones_documento', function (Blueprint $table) {
            $table->dropColumn(self::COLUMNAS);
        });

        Schema::table('aplicaciones_documento', function (Blueprint $table) {
            $table->renameColumn('valor_aplicado_centavos', 'valor_aplicado');
        });
    }

    public function down(): void
    {
        Schema::table('aplicaciones_documento', function (Blueprint $table) {
            $table->decimal('valor_aplicado_decimal', 14, 2)->default(0)->after('valor_aplicado');
        });

        DB::statement('UPDATE aplicaciones_documento SET valor_aplicado_decimal = valor_aplicado / 100');

        Schema::table('aplicaciones_documento', function (Blueprint $table) {
            $table->dropColumn(self::COLUMNAS);
        });

        Schema::table('aplicaciones_documento', function (Blueprint $table) {
            $table->renameColumn('valor_aplicado_decimal', 'valor_aplicado');
        });
    }
};
