<?php

namespace App\Correo\Jobs;

use App\Correo\Constants\CorreoConstants;
use App\Correo\Services\EnvioRecordatorioService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Throwable;

/** Viaja con el id y la empresa: el worker no tiene sesión y no lee nada que no venga de aquí. */
class EnviarRecordatorioCobranzaJob implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public int $timeout = CorreoConstants::TIMEOUT_JOB_SEGUNDOS;

    public int $tries = 1;

    public function __construct(
        public readonly int $envioId,
        public readonly int $empresaId,
    ) {
        $this->onConnection(CorreoConstants::CONEXION_COLA);
        $this->onQueue(CorreoConstants::COLA);
    }

    public function handle(EnvioRecordatorioService $recordatorios): void
    {
        $recordatorios->ejecutar($this->envioId, $this->empresaId);
    }

    public function failed(Throwable $excepcion): void
    {
        app(EnvioRecordatorioService::class)->marcarFallido($this->envioId, $this->empresaId);
    }
}
