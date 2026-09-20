<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Ver docblock de `2026_09_08_030000_migrar_montos_de_creditos_a_centavos`. */
return new class extends Migration
{
    private const COLUMNAS = ['valor'];

    public function up(): void
    {
        Schema::table('valores_uvt', function (Blueprint $table) {
            $table->bigInteger('valor_centavos')->default(0)->after('valor');
        });

        DB::statement('UPDATE valores_uvt SET valor_centavos = ROUND(valor * 100)');

        Schema::table('valores_uvt', function (Blueprint $table) {
            $table->dropColumn(self::COLUMNAS);
        });

        Schema::table('valores_uvt', function (Blueprint $table) {
            $table->renameColumn('valor_centavos', 'valor');
        });
    }

    public function down(): void
    {
        Schema::table('valores_uvt', function (Blueprint $table) {
            $table->decimal('valor_decimal', 10, 2)->default(0)->after('valor');
        });

        DB::statement('UPDATE valores_uvt SET valor_decimal = valor / 100');

        Schema::table('valores_uvt', function (Blueprint $table) {
            $table->dropColumn(self::COLUMNAS);
        });

        Schema::table('valores_uvt', function (Blueprint $table) {
            $table->renameColumn('valor_decimal', 'valor');
        });
    }
};
