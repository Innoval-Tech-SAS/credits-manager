<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reversas de egresos. Tabla de solo inserción: el egreso original nunca se edita ni se borra (sigue inmutable);
 * su corrección es una fila que lo apunta (`egreso_id`, único por empresa: un egreso se reversa una sola vez) y
 * el comprobante de reverso. Mismo patrón que `reversas_pago`.
 */
return new class extends Migration
{
    private const MENSAJE = 'reversas_egreso es de solo insercion';

    private const RESTRICCIONES = [
        'reversas_egreso_valor_check' => 'valor > 0',
        'reversas_egreso_motivo_check' => 'CHAR_LENGTH(TRIM(motivo)) > 0',
    ];

    public function up(): void
    {
        Schema::create('reversas_egreso', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->unsignedBigInteger('egreso_id');
            $table->unsignedBigInteger('comprobante_reverso_id')->nullable();
            $table->date('fecha');
            // Centavos, con DineroCast en el Model.
            $table->bigInteger('valor');
            $table->string('motivo', 500);
            $table->foreignId('registrado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamp('creado_en')->nullable();

            $table->unique(['empresa_id', 'id']);
            $table->unique(['empresa_id', 'egreso_id'], 'reversas_egreso_egreso_unico');

            $table->foreign(['empresa_id', 'egreso_id'])->references(['empresa_id', 'id'])->on('egresos');
            $table->foreign(['empresa_id', 'comprobante_reverso_id'])->references(['empresa_id', 'id'])->on('comprobantes');
        });

        $mensaje = self::MENSAJE;

        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER reversas_egreso_sin_update BEFORE UPDATE ON reversas_egreso BEGIN SELECT RAISE(ABORT, '{$mensaje}'); END");
            DB::unprepared("CREATE TRIGGER reversas_egreso_sin_delete BEFORE DELETE ON reversas_egreso BEGIN SELECT RAISE(ABORT, '{$mensaje}'); END");

            return;
        }

        DB::unprepared("CREATE TRIGGER reversas_egreso_sin_update BEFORE UPDATE ON reversas_egreso FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$mensaje}'");
        DB::unprepared("CREATE TRIGGER reversas_egreso_sin_delete BEFORE DELETE ON reversas_egreso FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$mensaje}'");

        foreach (self::RESTRICCIONES as $nombre => $condicion) {
            DB::statement("ALTER TABLE reversas_egreso ADD CONSTRAINT {$nombre} CHECK ({$condicion})");
        }
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS reversas_egreso_sin_update');
        DB::unprepared('DROP TRIGGER IF EXISTS reversas_egreso_sin_delete');

        Schema::dropIfExists('reversas_egreso');
    }
};
