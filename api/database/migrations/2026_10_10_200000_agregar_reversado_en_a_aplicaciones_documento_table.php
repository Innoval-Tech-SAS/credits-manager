<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca cuándo una aplicación (abono a un documento por pagar/cobrar) fue deshecha por una reversa. Cierra el
 * `TODO` de `GastoService::anular`: antes, cualquier aplicación —vigente o ya reversada— bloqueaba la anulación
 * del gasto para siempre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aplicaciones_documento', function (Blueprint $table) {
            $table->timestamp('reversado_en')->nullable()->after('fecha');
        });
    }

    public function down(): void
    {
        Schema::table('aplicaciones_documento', function (Blueprint $table) {
            $table->dropColumn('reversado_en');
        });
    }
};
