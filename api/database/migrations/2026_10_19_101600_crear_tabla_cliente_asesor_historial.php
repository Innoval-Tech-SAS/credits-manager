<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de solo inserción: sin `ultima_actualizacion` ni `borrado_en`. Todas las FK usan restrictOnDelete
 * (no cascade ni nullOnDelete) porque las acciones referenciales no disparan los triggers y mutarían una fila
 * inmutable. `asesor_anterior_id` es nulo solo en la asignación inicial.
 */
return new class extends Migration
{
    private const MENSAJE = 'cliente_asesor_historial es de solo insercion';

    public function up(): void
    {
        Schema::create('cliente_asesor_historial', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->restrictOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes')->restrictOnDelete();
            $table->foreignId('asesor_anterior_id')->nullable()->constrained('usuarios')->restrictOnDelete();
            $table->foreignId('asesor_id')->constrained('usuarios')->restrictOnDelete();
            $table->foreignId('asignado_por')->nullable()->constrained('usuarios')->restrictOnDelete();
            $table->string('motivo', 255)->nullable();
            $table->timestamp('creado_en')->nullable();

            $table->index(['empresa_id', 'cliente_id', 'creado_en']);
        });

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

        Schema::dropIfExists('cliente_asesor_historial');
    }
};
