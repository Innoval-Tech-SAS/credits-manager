<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de solo inserción (sin `BaseModel`, sin soft delete): una fila por ejecución ya terminada,
 * escrita una sola vez cuando el resultado ya se conoce (`RegistroEjecucionTarea`), nunca al iniciar.
 * Sin `empresa_id`: es infraestructura, no dato de negocio.
 */
return new class extends Migration
{
    private const MENSAJE = 'ejecuciones_tareas_programadas es de solo insercion';

    public function up(): void
    {
        Schema::create('ejecuciones_tareas_programadas', function (Blueprint $table) {
            $table->id();
            $table->string('tarea_clave', 60);
            $table->dateTime('iniciado_en', 3);
            $table->dateTime('finalizado_en', 3);
            $table->string('resultado', 20);
            $table->string('error', 500)->nullable();

            $table->index(['tarea_clave', 'iniciado_en']);
        });

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
        Schema::dropIfExists('ejecuciones_tareas_programadas');
    }
};
