<?php

namespace App\Conciliacion\Jobs;

use App\Conciliacion\Constants\ConciliacionConstants;
use App\Conciliacion\Services\ExtractoBancarioService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Throwable;

/** Viaja con el id y la empresa: el worker no tiene sesión y no lee nada que no venga de aquí. */
class ValidarExtractoBancarioJob implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public int $timeout = ConciliacionConstants::TIMEOUT_JOB_SEGUNDOS;

    public int $tries = 1;

    public function __construct(
        public readonly int $extractoBancarioId,
        public readonly int $empresaId,
    ) {
        $this->onConnection(ConciliacionConstants::CONEXION_COLA);
        $this->onQueue(ConciliacionConstants::COLA);
    }

    public function handle(ExtractoBancarioService $extractos): void
    {
        $extractos->procesar($this->extractoBancarioId, $this->empresaId);
    }

    public function failed(Throwable $excepcion): void
    {
        app(ExtractoBancarioService::class)->marcarFallida($this->extractoBancarioId, $this->empresaId);
    }
}
