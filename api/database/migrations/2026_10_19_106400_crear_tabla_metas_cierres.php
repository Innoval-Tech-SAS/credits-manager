<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cierres mensuales de metas. Tabla de solo inserción: un recálculo agrega una fila que apunta a la anterior
 * (`cierre_anterior_id`) en vez de editarla. `usuario_id` nulo significa el cierre de la meta de empresa (sin
 * asesor). `cerrado_en` hace de marca de tiempo de creación: no hay `creado_en` ni `ultima_actualizacion`.
 */
return new class extends Migration
{
    private const MENSAJE = 'metas_cierres es de solo insercion';

    public function up(): void
    {
        Schema::create('metas_cierres', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->unsignedBigInteger('meta_id');
            $table->string('periodo', 7);
            $table->string('tipo', 20);
            $table->unsignedBigInteger('asesor_id')->nullable();
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->restrictOnDelete();
            $table->foreignId('cierre_anterior_id')->nullable()->constrained('metas_cierres')->restrictOnDelete();
            $table->bigInteger('objetivo');
            $table->bigInteger('ejecutado');
            $table->decimal('porcentaje', 7, 2);
            $table->string('estado', 30);
            $table->string('motivo', 255)->nullable();
            $table->timestamp('cerrado_en');

            $table->index(['empresa_id', 'periodo', 'tipo']);
            $table->index(['empresa_id', 'meta_id', 'asesor_id']);
            $table->foreign(['empresa_id', 'meta_id'])->references(['empresa_id', 'id'])->on('metas')->restrictOnDelete();
            $table->foreign(['empresa_id', 'asesor_id'])->references(['empresa_id', 'id'])->on('usuarios')->restrictOnDelete();
        });

        $mensaje = self::MENSAJE;

        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER metas_cierres_sin_update BEFORE UPDATE ON metas_cierres BEGIN SELECT RAISE(ABORT, '{$mensaje}'); END");
            DB::unprepared("CREATE TRIGGER metas_cierres_sin_delete BEFORE DELETE ON metas_cierres BEGIN SELECT RAISE(ABORT, '{$mensaje}'); END");

            return;
        }

        DB::unprepared("CREATE TRIGGER metas_cierres_sin_update BEFORE UPDATE ON metas_cierres FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$mensaje}'");
        DB::unprepared("CREATE TRIGGER metas_cierres_sin_delete BEFORE DELETE ON metas_cierres FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$mensaje}'");
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS metas_cierres_sin_update');
        DB::unprepared('DROP TRIGGER IF EXISTS metas_cierres_sin_delete');

        Schema::dropIfExists('metas_cierres');
    }
};
