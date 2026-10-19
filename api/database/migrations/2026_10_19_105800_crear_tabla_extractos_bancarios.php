<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `fecha_desde`/`fecha_hasta` en vez de un único "período": un extracto real de banco puede traer varios
 * meses en un solo archivo, así que no hay un mes que lo identifique. Dos extractos vigentes
 * (`cargando`/`cargado`/`cerrado`) de la misma cuenta no pueden solaparse en fechas: esa unicidad NO es una
 * constraint de la base (no es una igualdad simple sino un solape de rangos, y además un `unique` bloquearía
 * reintentar la carga tras un `rechazado` — la alternativa, eliminarlo, violaría la regla de nunca hacer hard
 * delete desde código de aplicación) sino que la aplica `ExtractoBancarioService` antes de crear el registro;
 * un rechazado queda en la tabla como historial y nunca cuenta para ese solape.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('extractos_bancarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->unsignedBigInteger('cuenta_bancaria_id');
            $table->date('fecha_desde');
            $table->date('fecha_hasta');
            $table->bigInteger('saldo_anterior');
            $table->bigInteger('total_abonos');
            $table->bigInteger('total_cargos');
            $table->bigInteger('saldo_actual');
            $table->string('archivo_ruta', 255);
            $table->string('estado', 20);
            $table->string('motivo_rechazo', 500)->nullable();
            $table->foreignId('cargado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->foreignId('cerrado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamp('cerrado_en')->nullable();
            $table->foreignId('reabierto_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamp('reabierto_en')->nullable();
            $table->string('motivo_reapertura', 500)->nullable();
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('ultima_actualizacion')->nullable();
            $table->softDeletes('borrado_en');

            $table->unique(['empresa_id', 'id']);
            $table->index(['empresa_id', 'cuenta_bancaria_id', 'fecha_desde', 'fecha_hasta'], 'extractos_bancarios_rango_index');
            $table->index(['empresa_id', 'cuenta_bancaria_id', 'estado'], 'extractos_bancarios_estado_index');

            $table->foreign(['empresa_id', 'cuenta_bancaria_id'])->references(['empresa_id', 'id'])->on('cuentas_bancarias');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extractos_bancarios');
    }
};
