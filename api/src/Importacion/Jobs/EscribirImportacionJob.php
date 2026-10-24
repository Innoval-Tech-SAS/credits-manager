<?php

namespace App\Importacion\Jobs;

use App\Importacion\Constants\ImportacionConstants;
use App\Importacion\Services\ImportacionEjecutorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Throwable;

/** Escribe lo aceptado de una importación confirmada. Mismo contrato que `ValidarImportacionJob`. */
class EscribirImportacionJob implements ShouldQueue
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
        $ejecutor->escribir($this->importacionId, $this->empresaId);
    }

    public function failed(Throwable $excepcion): void
    {
        app(ImportacionEjecutorService::class)->marcarFallida($this->importacionId, $this->empresaId, $excepcion);
    }
}
