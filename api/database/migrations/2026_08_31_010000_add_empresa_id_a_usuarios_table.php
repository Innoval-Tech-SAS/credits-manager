<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->foreignId('empresa_id')->nullable()->after('id')->constrained('empresas')->cascadeOnDelete();
            $table->boolean('es_superadmin')->default(false)->after('activo');
            $table->dropForeign(['rol_id']);
            $table->dropColumn('rol_id');
        });

        Schema::table('usuarios', function (Blueprint $table) {
            $table->foreignId('rol_id')->nullable()->after('password_hash')->constrained('roles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropForeign(['rol_id']);
            $table->dropColumn('rol_id');
        });

        Schema::table('usuarios', function (Blueprint $table) {
            $table->foreignId('rol_id')->after('password_hash')->constrained('roles');
            $table->dropColumn('es_superadmin');
            $table->dropConstrainedForeignId('empresa_id');
        });
    }
};
