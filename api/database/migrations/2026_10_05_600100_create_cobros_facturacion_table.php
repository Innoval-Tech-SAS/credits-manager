<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cobros sobre facturas. Tabla de solo inserción: un cobro no se edita ni se borra; su corrección es una fila
 * «reversa» que apunta al cobro original (cobro_origen_id, único: un cobro se reversa una sola vez). El saldo
 * sale de la suma de cobros menos reversas.
 */
return new class extends Migration
{
    private const MENSAJE = 'cobros_facturacion es de solo insercion';

    private const RESTRICCIONES = [
        'cobros_facturacion_valor_check' => 'valor > 0',
        'cobros_facturacion_tipo_check' => "(tipo = 'cobro' AND cobro_origen_id IS NULL AND motivo IS NULL) OR (tipo = 'reversa' AND cobro_origen_id IS NOT NULL AND motivo IS NOT NULL)",
    ];

    public function up(): void
    {
        Schema::create('cobros_facturacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->unsignedBigInteger('documento_id');
            $table->string('tipo', 20);
            $table->unsignedBigInteger('cobro_origen_id')->nullable();
            $table->unsignedBigInteger('comprobante_id')->nullable();
            $table->date('fecha');
            // Centavos, con DineroCast en el Model.
            $table->bigInteger('valor');
            $table->string('medio', 30);
            $table->string('referencia', 60)->nullable();
            $table->string('observacion', 500)->nullable();
            $table->string('motivo', 500)->nullable();
            $table->foreignId('registrado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamp('creado_en')->nullable();

            $table->unique(['empresa_id', 'id']);
            $table->unique(['empresa_id', 'cobro_origen_id'], 'cobros_fac_reversa_unique');
            $table->index(['empresa_id', 'documento_id'], 'cobros_fac_documento_index');

            $table->foreign(['empresa_id', 'documento_id'])->references(['empresa_id', 'id'])->on('documentos_facturacion');
            $table->foreign(['empresa_id', 'cobro_origen_id'])->references(['empresa_id', 'id'])->on('cobros_facturacion');
            $table->foreign(['empresa_id', 'comprobante_id'])->references(['empresa_id', 'id'])->on('comprobantes');
        });

        $mensaje = self::MENSAJE;

        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER cobros_facturacion_sin_update BEFORE UPDATE ON cobros_facturacion BEGIN SELECT RAISE(ABORT, '{$mensaje}'); END");
            DB::unprepared("CREATE TRIGGER cobros_facturacion_sin_delete BEFORE DELETE ON cobros_facturacion BEGIN SELECT RAISE(ABORT, '{$mensaje}'); END");

            return;
        }

        DB::unprepared("CREATE TRIGGER cobros_facturacion_sin_update BEFORE UPDATE ON cobros_facturacion FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$mensaje}'");
        DB::unprepared("CREATE TRIGGER cobros_facturacion_sin_delete BEFORE DELETE ON cobros_facturacion FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$mensaje}'");

        foreach (self::RESTRICCIONES as $nombre => $condicion) {
            DB::statement("ALTER TABLE cobros_facturacion ADD CONSTRAINT {$nombre} CHECK ({$condicion})");
        }
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS cobros_facturacion_sin_update');
        DB::unprepared('DROP TRIGGER IF EXISTS cobros_facturacion_sin_delete');

        Schema::dropIfExists('cobros_facturacion');
    }
};
