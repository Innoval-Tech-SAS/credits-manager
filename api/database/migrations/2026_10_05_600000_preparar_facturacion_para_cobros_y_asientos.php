<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enlace factura → asiento. La FK compuesta exige una clave única (empresa_id, id) en comprobantes, así la base
 * impide que un documento apunte al comprobante de otra empresa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comprobantes', function (Blueprint $table) {
            $table->unique(['empresa_id', 'id']);
        });

        Schema::table('documentos_facturacion', function (Blueprint $table) {
            $table->unsignedBigInteger('comprobante_id')->nullable()->after('emitido_por');
            $table->foreign(['empresa_id', 'comprobante_id'])->references(['empresa_id', 'id'])->on('comprobantes');
        });
    }

    public function down(): void
    {
        Schema::table('documentos_facturacion', function (Blueprint $table) {
            $table->dropForeign(['empresa_id', 'comprobante_id']);
            $table->dropColumn('comprobante_id');
        });

        Schema::table('comprobantes', function (Blueprint $table) {
            $table->dropUnique(['empresa_id', 'id']);
        });
    }
};
