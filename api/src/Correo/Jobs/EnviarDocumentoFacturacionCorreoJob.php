<?php

namespace App\Correo\Jobs;

use App\Correo\Constants\CorreoConstants;
use App\Facturacion\Services\EnvioFacturacionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Throwable;

/**
 * Viaja solo con el id y la empresa: el worker no tiene sesión, así que el PDF se regenera dentro de `handle()`
 * (`EnvioFacturacionService::ejecutar()` → `DocumentoFacturacionService::generarPdfInterno()`), nunca viaja en el
 * payload del job. Antes el PDF binario viajaba como argumento y rompía la serialización JSON de la cola Redis.
 */
class EnviarDocumentoFacturacionCorreoJob implements ShouldQueue
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

    public function handle(EnvioFacturacionService $envios): void
    {
        $envios->ejecutar($this->envioId, $this->empresaId);
    }

    public function failed(Throwable $excepcion): void
    {
        app(EnvioFacturacionService::class)->marcarFallido($this->envioId, $this->empresaId);
    }
}
