<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->foreignId('rol_id')->after('password_hash')->constrained('roles');
            $table->dropColumn('rol');
        });
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->string('rol')->after('password_hash');
            $table->dropConstrainedForeignId('rol_id');
        });
    }
};
