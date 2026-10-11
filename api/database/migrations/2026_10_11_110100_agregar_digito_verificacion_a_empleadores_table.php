<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empleadores', function (Blueprint $table) {
            $table->string('digito_verificacion', 1)->nullable()->after('nit');
        });
    }

    public function down(): void
    {
        Schema::table('empleadores', function (Blueprint $table) {
            $table->dropColumn('digito_verificacion');
        });
    }
};
