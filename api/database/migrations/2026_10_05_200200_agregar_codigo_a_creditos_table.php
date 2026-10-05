<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Código de crédito asignado por DDA (o el consecutivo que ya usen). Null para los créditos creados en el sistema;
 * el índice por empresa solo cubre los importados, porque MySQL admite varios null en un índice único.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creditos', function (Blueprint $table) {
            $table->string('codigo', 40)->nullable()->after('cliente_id');
            $table->unique(['empresa_id', 'codigo']);
        });
    }

    public function down(): void
    {
        Schema::table('creditos', function (Blueprint $table) {
            $table->dropUnique(['empresa_id', 'codigo']);
            $table->dropColumn('codigo');
        });
    }
};
