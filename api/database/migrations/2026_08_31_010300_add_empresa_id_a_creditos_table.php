<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creditos', function (Blueprint $table) {
            $table->foreignId('empresa_id')->after('id')->constrained('empresas')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('creditos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('empresa_id');
        });
    }
};
