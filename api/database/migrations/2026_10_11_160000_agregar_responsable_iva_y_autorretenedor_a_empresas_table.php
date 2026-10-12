<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            // Régimen fiscal propio de la empresa como vendedora: decide el IVA y la autorretención de sus facturas.
            $table->boolean('responsable_iva')->default(false)->after('actividad_economica_id');
            $table->boolean('autorretenedor')->default(false)->after('responsable_iva');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn(['responsable_iva', 'autorretenedor']);
        });
    }
};
