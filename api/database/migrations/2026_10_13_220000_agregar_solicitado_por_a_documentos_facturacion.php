<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Quién pidió una nota crédito pendiente de aprobación (7.1.2) — distinto de `emitido_por`, que queda registrado al aprobarla. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documentos_facturacion', function (Blueprint $table) {
            $table->foreignId('solicitado_por')->nullable()->after('motivo')->constrained('usuarios')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('documentos_facturacion', function (Blueprint $table) {
            $table->dropConstrainedForeignId('solicitado_por');
        });
    }
};
