<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `marcarVencidas()`, `pendientesDeCreditosActivos()` y `venceHoyDeCreditosActivos()`
 * (CuotaRepository) filtran `cuotas` por `estado`/`fecha_vencimiento` en cada carga de
 * Dashboard/Cartera/Calendario/Créditos, y `CreditoRepository::paginar/activosConCliente/
 * contarActivos` filtran `creditos` por `estado`; ninguna de las dos columnas tenía índice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cuotas', function (Blueprint $table) {
            $table->index(['estado', 'fecha_vencimiento']);
        });

        Schema::table('creditos', function (Blueprint $table) {
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::table('cuotas', function (Blueprint $table) {
            $table->dropIndex(['estado', 'fecha_vencimiento']);
        });

        Schema::table('creditos', function (Blueprint $table) {
            $table->dropIndex(['estado']);
        });
    }
};
