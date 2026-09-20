<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tipos_comprobante', function (Blueprint $table) {
            $table->dropUnique(['codigo']);
            $table->foreignId('empresa_id')->after('id')->constrained('empresas')->cascadeOnDelete();
            $table->unique(['empresa_id', 'codigo']);
        });
    }

    public function down(): void
    {
        Schema::table('tipos_comprobante', function (Blueprint $table) {
            $table->dropUnique(['empresa_id', 'codigo']);
            $table->dropConstrainedForeignId('empresa_id');
            $table->unique('codigo');
        });
    }
};
