<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('terceros', function (Blueprint $table) {
            $table->string('nombres', 100)->nullable()->after('nombre');
            $table->string('apellidos', 100)->nullable()->after('nombres');
            $table->string('razon_social', 150)->nullable()->after('apellidos');
            $table->string('digito_verificacion', 1)->nullable()->after('razon_social');
            $table->string('representante_legal', 150)->nullable()->after('digito_verificacion');
        });
    }

    public function down(): void
    {
        Schema::table('terceros', function (Blueprint $table) {
            $table->dropColumn(['nombres', 'apellidos', 'razon_social', 'digito_verificacion', 'representante_legal']);
        });
    }
};
