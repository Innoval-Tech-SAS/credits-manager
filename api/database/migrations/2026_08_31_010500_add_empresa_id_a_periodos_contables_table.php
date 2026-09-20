<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('periodos_contables', function (Blueprint $table) {
            $table->dropUnique(['anio', 'mes']);
            $table->foreignId('empresa_id')->after('id')->constrained('empresas')->cascadeOnDelete();
            $table->unique(['empresa_id', 'anio', 'mes']);
        });
    }

    public function down(): void
    {
        Schema::table('periodos_contables', function (Blueprint $table) {
            $table->dropUnique(['empresa_id', 'anio', 'mes']);
            $table->dropConstrainedForeignId('empresa_id');
            $table->unique(['anio', 'mes']);
        });
    }
};
