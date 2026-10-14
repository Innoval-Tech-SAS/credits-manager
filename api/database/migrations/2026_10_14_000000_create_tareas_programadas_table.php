<?php

use App\Monitoreo\Constants\TareaProgramadaConstants;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo fijo de tareas programadas (infraestructura, sin `empresa_id`, igual que `roles`/`paises`):
 * el superadmin solo cambia `hora`/`activa` de las 6 filas que esta migración siembra, nunca crea ni
 * borra una. El comando real de cada clave vive en `routes/console.php`, no aquí.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tareas_programadas', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 60)->unique();
            $table->time('hora');
            $table->boolean('activa')->default(true);
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('ultima_actualizacion')->nullable();
        });

        $ahora = now();

        foreach (TareaProgramadaConstants::HORAS_POR_DEFECTO as $clave => $hora) {
            DB::table('tareas_programadas')->insert([
                'clave' => $clave,
                'hora' => $hora,
                'activa' => true,
                'creado_en' => $ahora,
                'ultima_actualizacion' => $ahora,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tareas_programadas');
    }
};
