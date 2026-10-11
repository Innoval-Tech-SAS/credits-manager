<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** El cuerpo se congela igual que ya hace `envios_correo.cuerpo`: antes el mensaje de facturación era fijo en código. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('envios_facturacion', function (Blueprint $table) {
            $table->foreignId('plantilla_correo_id')->nullable()->after('asunto')->constrained('plantillas_correo')->nullOnDelete();
            $table->longText('cuerpo')->nullable()->after('plantilla_correo_id');
        });
    }

    public function down(): void
    {
        Schema::table('envios_facturacion', function (Blueprint $table) {
            $table->dropColumn('cuerpo');
            $table->dropConstrainedForeignId('plantilla_correo_id');
        });
    }
};
