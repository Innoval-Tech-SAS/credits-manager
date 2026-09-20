<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique(['nombre']);
            $table->foreignId('empresa_id')->after('id')->constrained('empresas')->cascadeOnDelete();
            $table->unique(['empresa_id', 'nombre']);
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique(['empresa_id', 'nombre']);
            $table->dropConstrainedForeignId('empresa_id');
            $table->unique('nombre');
        });
    }
};
