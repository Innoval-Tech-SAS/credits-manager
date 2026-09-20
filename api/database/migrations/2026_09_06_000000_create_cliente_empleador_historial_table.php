<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cliente_empleador_historial', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->foreignId('empleador_id')->constrained('empleadores');
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('ultima_actualizacion')->nullable();
            $table->softDeletes('borrado_en');
        });

        // Backfill: cada cliente con empleador_id ya asignado obtiene su primer (y hasta ahora único) período vigente.
        DB::table('clientes')
            ->whereNotNull('empleador_id')
            ->select('id', 'empresa_id', 'empleador_id', 'creado_en')
            ->orderBy('id')
            ->each(function (object $cliente) {
                DB::table('cliente_empleador_historial')->insert([
                    'empresa_id' => $cliente->empresa_id,
                    'cliente_id' => $cliente->id,
                    'empleador_id' => $cliente->empleador_id,
                    'fecha_inicio' => $cliente->creado_en ? date('Y-m-d', strtotime($cliente->creado_en)) : now()->toDateString(),
                    'fecha_fin' => null,
                    'creado_en' => now(),
                    'ultima_actualizacion' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('cliente_empleador_historial');
    }
};
