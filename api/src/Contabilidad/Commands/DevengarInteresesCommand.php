<?php

namespace App\Contabilidad\Commands;

use App\Contabilidad\Services\DevengoInteresService;
use App\Empresas\Models\Empresa;
use Illuminate\Console\Command;

/** Devenga el interés corrido de cada empresa hasta hoy (M15), empresa por empresa. Lo corre el scheduler una vez al mes. */
class DevengarInteresesCommand extends Command
{
    protected $signature = 'contabilidad:devengar-intereses';

    protected $description = 'Causa el interés corrido de las cuotas vencidas sin devengar de todas las empresas';

    public function handle(DevengoInteresService $devengo): int
    {
        $hasta = now()->toDateString();
        $totalDevengadas = 0;
        $totalSuspendidas = 0;

        foreach (Empresa::query()->pluck('id') as $empresaId) {
            $resultado = $devengo->devengarHasta((int) $empresaId, $hasta);
            $totalDevengadas += $resultado['cuotas_devengadas'];
            $totalSuspendidas += $resultado['cuotas_suspendidas_por_mora'];
        }

        $this->info("Devengo de intereses completado. Cuotas devengadas: {$totalDevengadas}. Suspendidas por mora: {$totalSuspendidas}.");

        return self::SUCCESS;
    }
}
