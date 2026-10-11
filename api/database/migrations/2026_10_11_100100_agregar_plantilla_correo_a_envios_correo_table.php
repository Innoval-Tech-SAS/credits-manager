<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** `plantilla` (string fija, siempre `recordatorio_cobranza`) queda reemplazada por la FK, que además trae nombre e historial real. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('envios_correo', function (Blueprint $table) {
            $table->foreignId('plantilla_correo_id')->nullable()->after('cuota_id')->constrained('plantillas_correo')->nullOnDelete();
        });

        Schema::table('envios_correo', function (Blueprint $table) {
            $table->dropColumn('plantilla');
        });
    }

    public function down(): void
    {
        Schema::table('envios_correo', function (Blueprint $table) {
            $table->string('plantilla', 40)->default('recordatorio_cobranza');
        });

        Schema::table('envios_correo', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plantilla_correo_id');
        });
    }
};
