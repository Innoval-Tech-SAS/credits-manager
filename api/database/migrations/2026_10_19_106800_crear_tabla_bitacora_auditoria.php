<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de solo inserción: sin `ultima_actualizacion` ni `borrado_en`. `empresa_id` usa
 * restrictOnDelete (no cascade) porque las acciones en cascada de FK no disparan los
 * triggers de abajo y borrarían bitácora en silencio. `usuario_id` no lleva FK: es
 * referencia de snapshot (el autor real está en `autor_*`), y un nullOnDelete mutaría
 * una fila inmutable.
 */
return new class extends Migration
{
    private const MENSAJE = 'bitacora_auditoria es de solo insercion';

    public function up(): void
    {
        Schema::create('bitacora_auditoria', function (Blueprint $table) {
            $table->id();
            $table->uuid('evento_id')->unique();
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->restrictOnDelete();

            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->string('autor_nombre')->nullable();
            $table->string('autor_usuario')->nullable();
            $table->string('autor_rol')->nullable();
            $table->boolean('es_superadmin')->default(false);

            $table->string('modulo', 50);
            $table->string('accion', 30);
            $table->string('descripcion', 500);
            $table->text('motivo')->nullable();

            $table->string('entidad', 60)->nullable();
            $table->unsignedBigInteger('entidad_id')->nullable();
            $table->json('valores_anteriores')->nullable();
            $table->json('valores_nuevos')->nullable();

            $table->string('ip', 45)->nullable();
            $table->dateTime('fecha_hora', 3);
            $table->dateTime('creado_en', 3);

            $table->index(['empresa_id', 'fecha_hora']);
            $table->index(['empresa_id', 'modulo', 'fecha_hora']);
            $table->index(['empresa_id', 'accion', 'fecha_hora']);
            $table->index(['empresa_id', 'entidad', 'entidad_id']);
            $table->index('fecha_hora');
        });

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
        Schema::dropIfExists('bitacora_auditoria');
    }
};
