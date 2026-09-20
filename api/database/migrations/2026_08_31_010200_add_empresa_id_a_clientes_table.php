<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropUnique(['numero_documento']);
            $table->foreignId('empresa_id')->after('id')->constrained('empresas')->cascadeOnDelete();
            $table->unique(['empresa_id', 'numero_documento']);
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropUnique(['empresa_id', 'numero_documento']);
            $table->dropConstrainedForeignId('empresa_id');
            $table->unique('numero_documento');
        });
    }
};
