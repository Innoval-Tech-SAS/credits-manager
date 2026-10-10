<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Permite que un cobro de factura (`cobros_facturacion`) también emita un recibo de caja, no solo un pago de
 * crédito. `pago_id` deja de ser obligatorio; `origen_tipo` dice cuál de los dos aplica, con un `CHECK` que exige
 * exactamente uno de los dos (mismo patrón discriminador que `cobros_facturacion_tipo_check`).
 */
return new class extends Migration
{
    private const MENSAJE = 'recibos_caja exige exactamente un origen (pago o cobro de facturacion)';

    public function up(): void
    {
        // `pago_id` pasa a nullable sin doctrine/dbal: columna nueva, copia, se baja la vieja (igual patrón que
        // las migraciones de conversión a centavos).
        Schema::table('recibos_caja', function (Blueprint $table) {
            $table->unsignedBigInteger('pago_id_nullable')->nullable()->after('empresa_id');
        });

        DB::statement('UPDATE recibos_caja SET pago_id_nullable = pago_id');

        Schema::table('recibos_caja', function (Blueprint $table) {
            $table->dropForeign(['empresa_id', 'pago_id']);
            $table->dropUnique('recibos_caja_pago_id_unique');
            $table->dropColumn('pago_id');
        });

        Schema::table('recibos_caja', function (Blueprint $table) {
            $table->renameColumn('pago_id_nullable', 'pago_id');
        });

        Schema::table('recibos_caja', function (Blueprint $table) {
            $table->string('origen_tipo', 20)->default('pago')->after('pago_id');
            $table->unsignedBigInteger('cobro_facturacion_id')->nullable()->after('origen_tipo');

            $table->unique(['empresa_id', 'pago_id']);
            $table->unique(['empresa_id', 'cobro_facturacion_id']);

            $table->foreign(['empresa_id', 'pago_id'])->references(['empresa_id', 'id'])->on('pagos');
            $table->foreign(['empresa_id', 'cobro_facturacion_id'])->references(['empresa_id', 'id'])->on('cobros_facturacion');
        });

        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement(
            "ALTER TABLE recibos_caja ADD CONSTRAINT recibos_caja_origen_check CHECK (".
            "(origen_tipo = 'pago' AND pago_id IS NOT NULL AND cobro_facturacion_id IS NULL) ".
            "OR (origen_tipo = 'cobro_facturacion' AND cobro_facturacion_id IS NOT NULL AND pago_id IS NULL)".
            ')',
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE recibos_caja DROP CONSTRAINT recibos_caja_origen_check');
        }

        Schema::table('recibos_caja', function (Blueprint $table) {
            $table->dropForeign(['empresa_id', 'cobro_facturacion_id']);
            $table->dropForeign(['empresa_id', 'pago_id']);
            $table->dropUnique(['empresa_id', 'cobro_facturacion_id']);
            $table->dropUnique(['empresa_id', 'pago_id']);
            $table->dropColumn(['cobro_facturacion_id', 'origen_tipo']);
        });

        Schema::table('recibos_caja', function (Blueprint $table) {
            $table->unsignedBigInteger('pago_id_notnull')->after('empresa_id')->default(0);
        });

        DB::statement('UPDATE recibos_caja SET pago_id_notnull = pago_id');

        Schema::table('recibos_caja', function (Blueprint $table) {
            $table->dropColumn('pago_id');
        });

        Schema::table('recibos_caja', function (Blueprint $table) {
            $table->renameColumn('pago_id_notnull', 'pago_id');
        });

        Schema::table('recibos_caja', function (Blueprint $table) {
            $table->unique('pago_id');
            $table->foreign(['empresa_id', 'pago_id'])->references(['empresa_id', 'id'])->on('pagos');
        });
    }
};
