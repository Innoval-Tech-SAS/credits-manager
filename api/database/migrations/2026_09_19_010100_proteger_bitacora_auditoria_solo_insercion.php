<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La base rechaza UPDATE y DELETE sobre la bitácora, además del guardia del modelo.
 * Nota: TRUNCATE y DROP TABLE no activan triggers; se cubren con privilegios de la BD.
 */
return new class extends Migration
{
    private const MENSAJE = 'bitacora_auditoria es de solo insercion';

    public function up(): void
    {
        $mensaje = self::MENSAJE;

        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER bitacora_auditoria_sin_update BEFORE UPDATE ON bitacora_auditoria BEGIN SELECT RAISE(ABORT, '{$mensaje}'); END");
            DB::unprepared("CREATE TRIGGER bitacora_auditoria_sin_delete BEFORE DELETE ON bitacora_auditoria BEGIN SELECT RAISE(ABORT, '{$mensaje}'); END");

            return;
        }

        DB::unprepared("CREATE TRIGGER bitacora_auditoria_sin_update BEFORE UPDATE ON bitacora_auditoria FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$mensaje}'");
        DB::unprepared("CREATE TRIGGER bitacora_auditoria_sin_delete BEFORE DELETE ON bitacora_auditoria FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$mensaje}'");
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS bitacora_auditoria_sin_update');
        DB::unprepared('DROP TRIGGER IF EXISTS bitacora_auditoria_sin_delete');
    }
};
