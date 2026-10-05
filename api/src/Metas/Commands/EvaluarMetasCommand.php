<?php

namespace App\Metas\Commands;

use App\Empresas\Models\Empresa;
use App\Metas\Constants\MetasConstants;
use App\Metas\Services\EvaluacionMetasService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/** Cierra las metas vencidas y genera alertas, empresa por empresa. Lo corre el scheduler una vez al día. */
class EvaluarMetasCommand extends Command
{
    protected $signature = 'metas:evaluar';

    protected $description = 'Cierra las metas vencidas y genera las alertas de metas de todas las empresas';

    public function handle(EvaluacionMetasService $evaluacion): int
    {
        $ahora = CarbonImmutable::now(MetasConstants::ZONA_HORARIA);
        $cerradas = 0;

        foreach (Empresa::query()->pluck('id') as $empresaId) {
            $cerradas += $evaluacion->evaluarEmpresa((int) $empresaId, $ahora);
        }

        $this->info("Metas evaluadas. Cerradas en esta corrida: {$cerradas}.");

        return self::SUCCESS;
    }
}
