<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Prerrequisito para la FK compuesta de `reversas_egreso` hacia `egresos` (InnoDB/SQLite exigen un índice único exacto sobre las columnas referenciadas). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('egresos', function (Blueprint $table) {
            $table->unique(['empresa_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::table('egresos', function (Blueprint $table) {
            $table->dropUnique(['empresa_id', 'id']);
        });
    }
};
