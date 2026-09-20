<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('terceros', function (Blueprint $table) {
            $table->dropUnique(['tipo_documento', 'numero_documento']);
            $table->foreignId('empresa_id')->after('id')->constrained('empresas')->cascadeOnDelete();
            $table->unique(['empresa_id', 'tipo_documento', 'numero_documento']);
        });
    }

    public function down(): void
    {
        Schema::table('terceros', function (Blueprint $table) {
            $table->dropUnique(['empresa_id', 'tipo_documento', 'numero_documento']);
            $table->dropConstrainedForeignId('empresa_id');
            $table->unique(['tipo_documento', 'numero_documento']);
        });
    }
};
