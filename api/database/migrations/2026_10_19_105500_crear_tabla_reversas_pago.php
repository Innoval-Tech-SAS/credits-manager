<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reversas de pagos a crédito. Tabla de solo inserción: el pago original nunca se edita ni se borra; su corrección
 * es una fila que lo apunta (`pago_id`, único por empresa: un pago se reversa una sola vez) y el comprobante de
 * reverso. `comprobante_reverso_id` apunta a `comprobantes` (módulo Contabilidad, creada más adelante): FK simple,
 * no compuesta por empresa, declarada igual aunque la tabla destino todavía no exista en este punto del orden.
 */
return new class extends Migration
{
    private const MENSAJE = 'reversas_pago es de solo insercion';

    private const RESTRICCIONES = [
        'reversas_pago_valor_check' => 'valor > 0',
        'reversas_pago_motivo_check' => 'CHAR_LENGTH(TRIM(motivo)) > 0',
    ];

    public function up(): void
    {
        Schema::create('reversas_pago', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->restrictOnDelete();
            $table->unsignedBigInteger('pago_id');
            $table->foreignId('comprobante_reverso_id')->nullable()->constrained('comprobantes')->nullOnDelete();
            $table->date('fecha');
            // Centavos, con DineroCast en el Model.
            $table->bigInteger('valor');
            $table->string('motivo', 500);
            $table->foreignId('registrado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamp('creado_en')->nullable();

            $table->unique(['empresa_id', 'id']);
            $table->unique(['empresa_id', 'pago_id'], 'reversas_pago_pago_unico');

            $table->foreign(['empresa_id', 'pago_id'])->references(['empresa_id', 'id'])->on('pagos');
        });

        $mensaje = self::MENSAJE;

        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER reversas_pago_sin_update BEFORE UPDATE ON reversas_pago BEGIN SELECT RAISE(ABORT, '{$mensaje}'); END");
            DB::unprepared("CREATE TRIGGER reversas_pago_sin_delete BEFORE DELETE ON reversas_pago BEGIN SELECT RAISE(ABORT, '{$mensaje}'); END");

            return;
        }

        DB::unprepared("CREATE TRIGGER reversas_pago_sin_update BEFORE UPDATE ON reversas_pago FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$mensaje}'");
        DB::unprepared("CREATE TRIGGER reversas_pago_sin_delete BEFORE DELETE ON reversas_pago FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$mensaje}'");

        foreach (self::RESTRICCIONES as $nombre => $condicion) {
            DB::statement("ALTER TABLE reversas_pago ADD CONSTRAINT {$nombre} CHECK ({$condicion})");
        }
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS reversas_pago_sin_update');
        DB::unprepared('DROP TRIGGER IF EXISTS reversas_pago_sin_delete');

        Schema::dropIfExists('reversas_pago');
    }
};
