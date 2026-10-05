<?php

namespace App\Importacion\Jobs;

use App\Importacion\Constants\ImportacionConstants;
use App\Importacion\Services\ImportacionEjecutorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Throwable;

/** Viaja con el id y la empresa: el worker no tiene sesión y no lee nada que no venga de aquí. */
class ValidarImportacionJob implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public int $timeout = ImportacionConstants::TIMEOUT_JOB_SEGUNDOS;

    public int $tries = 1;

    public function __construct(
        public readonly int $importacionId,
        public readonly int $empresaId,
    ) {
        $this->onConnection(ImportacionConstants::CONEXION_COLA);
        $this->onQueue(ImportacionConstants::COLA);
    }

    public function handle(ImportacionEjecutorService $ejecutor): void
    {
        $ejecutor->validar($this->importacionId, $this->empresaId);
    }

    public function failed(Throwable $excepcion): void
    {
        app(ImportacionEjecutorService::class)->marcarFallida($this->importacionId, $this->empresaId, $excepcion);
    }
}
