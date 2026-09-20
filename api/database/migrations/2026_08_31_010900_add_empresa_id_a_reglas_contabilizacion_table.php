<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reglas_contabilizacion', function (Blueprint $table) {
            $table->dropUnique(['evento']);
            $table->foreignId('empresa_id')->after('id')->constrained('empresas')->cascadeOnDelete();
            $table->unique(['empresa_id', 'evento']);
        });
    }

    public function down(): void
    {
        Schema::table('reglas_contabilizacion', function (Blueprint $table) {
            $table->dropUnique(['empresa_id', 'evento']);
            $table->dropConstrainedForeignId('empresa_id');
            $table->unique('evento');
        });
    }
};
