<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('terceros', function (Blueprint $table) {
            $table->string('direccion')->nullable()->after('nombre');
            $table->string('barrio')->nullable()->after('direccion');
            $table->string('ciudad')->nullable()->after('barrio');
            $table->string('telefono_fijo', 30)->after('celular')->nullable();
            $table->dropColumn('tipo');
        });
    }

    public function down(): void
    {
        Schema::table('terceros', function (Blueprint $table) {
            $table->string('tipo')->default('otro')->after('nombre');
            $table->dropColumn(['direccion', 'barrio', 'ciudad', 'telefono_fijo']);
        });
    }
};
