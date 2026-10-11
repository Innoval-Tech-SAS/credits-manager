<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Corrige créditos existentes que ya tienen un % de gastos administrativos definido en
     * la base de datos, recalculando el valor de otros gastos para las cuotas pendientes.
     *
     * No cambia créditos con 0% ni altera cuotas ya pagadas, porque la regla del sistema
     * es recalcular solo las cuotas no pagadas y dejar intacto el historial de pagos.
     */
    public function up(): void
    {
        $creditos = DB::table('creditos')
            ->select(['id', 'valor_prestamo', 'gastos_administracion_pct'])
            ->where('gastos_administracion_pct', '>', 0)
            ->get();

        foreach ($creditos as $credito) {
            $porcentaje = (float) $credito->gastos_administracion_pct;
            $capital = (int) $credito->valor_prestamo;

            if ($porcentaje <= 0 || $capital <= 0) {
                continue;
            }

            $otrosGastosPorCuota = (int) round($capital * ($porcentaje / 100));

            DB::table('cuotas')
                ->where('credito_id', $credito->id)
                ->where(function ($query) {
                    $query->where('valor_pagado', 0)->orWhereNull('valor_pagado');
                })
                ->update(['valor_otros_gastos' => $otrosGastosPorCuota]);
        }
    }

    public function down(): void
    {
        // No se restaura el historial porque la corrección debe ser idempotente y el valor
        // de otros gastos de cada cuota no es un dato derivable de forma segura a partir
        // del original sin perder el estado de pagos anteriores.
    }
};
