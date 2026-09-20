<?php

namespace App\Exportacion\Jobs;

use App\Exportacion\Constants\ExportacionConstants;
use App\Exportacion\Contracts\FuenteExportable;
use App\Exportacion\Enums\FormatoExportacion;
use App\Exportacion\Services\ExportacionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Throwable;

/** Viaja con la fuente serializada: lleva su visibilidad y filtros, porque el worker no tiene sesión. */
class ProcesarExportacionJob implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public int $timeout = ExportacionConstants::TIMEOUT_JOB_SEGUNDOS;

    public int $tries = 1;

    public function __construct(
        public readonly int $exportacionId,
        public readonly ?int $empresaId,
        public readonly FuenteExportable $fuente,
        public readonly FormatoExportacion $formato,
    ) {
        $this->onConnection(ExportacionConstants::CONEXION_COLA);
        $this->onQueue(ExportacionConstants::COLA);
    }

    public function handle(ExportacionService $exportaciones): void
    {
        $exportaciones->procesar($this->exportacionId, $this->empresaId, $this->fuente, $this->formato);
    }

    public function failed(Throwable $excepcion): void
    {
        app(ExportacionService::class)->marcarFallida($this->exportacionId, $this->empresaId, $this->fuente->titulo(), $excepcion);
    }
}
