<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** La base rechaza UPDATE y DELETE sobre el historial, además del guardia del modelo. */
return new class extends Migration
{
    private const MENSAJE = 'cliente_asesor_historial es de solo insercion';

    public function up(): void
    {
        $mensaje = self::MENSAJE;

        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER cliente_asesor_historial_sin_update BEFORE UPDATE ON cliente_asesor_historial BEGIN SELECT RAISE(ABORT, '{$mensaje}'); END");
            DB::unprepared("CREATE TRIGGER cliente_asesor_historial_sin_delete BEFORE DELETE ON cliente_asesor_historial BEGIN SELECT RAISE(ABORT, '{$mensaje}'); END");

            return;
        }

        DB::unprepared("CREATE TRIGGER cliente_asesor_historial_sin_update BEFORE UPDATE ON cliente_asesor_historial FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$mensaje}'");
        DB::unprepared("CREATE TRIGGER cliente_asesor_historial_sin_delete BEFORE DELETE ON cliente_asesor_historial FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$mensaje}'");
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS cliente_asesor_historial_sin_update');
        DB::unprepared('DROP TRIGGER IF EXISTS cliente_asesor_historial_sin_delete');
    }
};
