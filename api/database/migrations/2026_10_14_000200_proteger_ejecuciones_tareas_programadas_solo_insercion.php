<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La base rechaza UPDATE y DELETE sobre las ejecuciones de tareas programadas, además del guardia del
 * modelo. Nota: TRUNCATE y DROP TABLE no activan triggers; se cubren con privilegios de la BD.
 */
return new class extends Migration
{
    private const MENSAJE = 'ejecuciones_tareas_programadas es de solo insercion';

    public function up(): void
    {
        $mensaje = self::MENSAJE;

        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER ejecuciones_tareas_programadas_sin_update BEFORE UPDATE ON ejecuciones_tareas_programadas BEGIN SELECT RAISE(ABORT, '{$mensaje}'); END");
            DB::unprepared("CREATE TRIGGER ejecuciones_tareas_programadas_sin_delete BEFORE DELETE ON ejecuciones_tareas_programadas BEGIN SELECT RAISE(ABORT, '{$mensaje}'); END");

            return;
        }

        DB::unprepared("CREATE TRIGGER ejecuciones_tareas_programadas_sin_update BEFORE UPDATE ON ejecuciones_tareas_programadas FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$mensaje}'");
        DB::unprepared("CREATE TRIGGER ejecuciones_tareas_programadas_sin_delete BEFORE DELETE ON ejecuciones_tareas_programadas FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$mensaje}'");
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS ejecuciones_tareas_programadas_sin_update');
        DB::unprepared('DROP TRIGGER IF EXISTS ejecuciones_tareas_programadas_sin_delete');
    }
};
