<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('direccion')->nullable()->after('numero_documento');
            $table->string('barrio')->nullable()->after('direccion');
            $table->string('ciudad')->nullable()->after('barrio');
            $table->string('celular')->nullable()->after('ciudad');
            $table->string('telefono_fijo')->nullable()->after('celular');
        });

        DB::table('clientes')->update(['celular' => DB::raw('telefono')]);

        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn('telefono');
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('telefono')->nullable()->after('numero_documento');
        });

        DB::table('clientes')->update(['telefono' => DB::raw('celular')]);

        Schema::table('clientes', function (Blueprint $table) {
            $table->string('telefono')->nullable(false)->change();
            $table->dropColumn(['direccion', 'barrio', 'ciudad', 'celular', 'telefono_fijo']);
        });
    }
};
