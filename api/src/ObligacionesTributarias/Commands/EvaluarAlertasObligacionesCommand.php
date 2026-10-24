<?php

namespace App\ObligacionesTributarias\Commands;

use App\Empresas\Models\Empresa;
use App\ObligacionesTributarias\Constants\ObligacionesTributariasConstants;
use App\ObligacionesTributarias\Services\AlertaObligacionTributariaService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/** Genera las alertas de obligaciones tributarias por vencer, empresa por empresa. Lo corre el scheduler una vez al día. */
class EvaluarAlertasObligacionesCommand extends Command
{
    protected $signature = 'obligaciones_tributarias:evaluar-alertas';

    protected $description = 'Genera las alertas de obligaciones tributarias por vencer de todas las empresas';

    public function handle(AlertaObligacionTributariaService $alertas): int
    {
        $hoy = CarbonImmutable::now(ObligacionesTributariasConstants::ZONA_HORARIA);
        $generadas = 0;

        foreach (Empresa::query()->pluck('id') as $empresaId) {
            $generadas += $alertas->generarParaEmpresa((int) $empresaId, $hoy);
        }

        $this->info("Alertas de obligaciones tributarias evaluadas. Generadas en esta corrida: {$generadas}.");

        return self::SUCCESS;
    }
}
