<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Revisión de asientos manuales: borrador → revisado → contabilizado. `revisado_por` debe ser distinto de
 * `creado_por` (la regla la aplica `ComprobanteService::revisar()`). Los comprobantes existentes ya están
 * contabilizados y no cambian.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comprobantes', function (Blueprint $table) {
            $table->foreignId('revisado_por')->nullable()->after('contabilizado_en')->constrained('usuarios')->nullOnDelete();
            $table->timestamp('revisado_en')->nullable()->after('revisado_por');
        });
    }

    public function down(): void
    {
        Schema::table('comprobantes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('revisado_por');
            $table->dropColumn('revisado_en');
        });
    }
};
