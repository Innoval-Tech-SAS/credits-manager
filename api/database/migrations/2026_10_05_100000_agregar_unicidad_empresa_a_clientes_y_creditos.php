<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prerrequisito de las FK compuestas (empresa_id, id) de los documentos de facturación: la base rechaza un
 * documento que apunte a un cliente o a un crédito de otra empresa. Mismo patrón que `pagos` en recibos_caja.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->unique(['empresa_id', 'id']);
        });

        Schema::table('creditos', function (Blueprint $table) {
            $table->unique(['empresa_id', 'id']);
        });
    }

    public function down(): void
    {
        // La FK de empresa_id pudo apoyarse en el índice único: MySQL no lo suelta mientras esa FK no tenga índice propio.
        foreach (['creditos', 'clientes'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) use ($tabla) {
                if (! Schema::hasIndex($tabla, ['empresa_id'])) {
                    $table->index('empresa_id');
                }

                $table->dropUnique(['empresa_id', 'id']);
            });
        }
    }
};
