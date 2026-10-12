<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El Super Admin pasa a ser un rol: la única fila de `roles` sin empresa. `clave_plataforma` vale 1 solo para esa
 * fila y el índice único impide una segunda (los `NULL` de los roles de empresa no chocan). Es VIRTUAL: MySQL solo
 * prohíbe leer una columna con FK en CASCADE desde una columna generada STORED.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->foreignId('empresa_id')->nullable()->change();
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->unsignedTinyInteger('clave_plataforma')
                ->virtualAs('case when empresa_id is null then 1 end')
                ->nullable();
            $table->unique('clave_plataforma');
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique(['clave_plataforma']);
            $table->dropColumn('clave_plataforma');
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->foreignId('empresa_id')->nullable(false)->change();
        });
    }
};
