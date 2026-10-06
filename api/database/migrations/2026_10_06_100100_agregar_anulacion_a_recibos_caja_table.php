<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Anulación del recibo por reverso del pago: conserva su número, y la fecha, el usuario y el motivo quedan en la fila. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recibos_caja', function (Blueprint $table) {
            $table->timestamp('anulado_en')->nullable()->after('emitido_por');
            $table->foreignId('anulado_por')->nullable()->after('anulado_en')->constrained('usuarios')->nullOnDelete();
            $table->string('motivo_anulacion', 500)->nullable()->after('anulado_por');
        });
    }

    public function down(): void
    {
        Schema::table('recibos_caja', function (Blueprint $table) {
            $table->dropForeign(['anulado_por']);
            $table->dropColumn(['anulado_en', 'anulado_por', 'motivo_anulacion']);
        });
    }
};
