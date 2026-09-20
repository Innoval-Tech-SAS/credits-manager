<?php

namespace Tests\Concerns;

use App\Auditoria\Constants\AuditoriaConstants;
use Illuminate\Support\Facades\Log;

/** Apunta el log de respaldo de auditoría a un directorio temporal propio de cada test. */
trait UsaRespaldoAuditoriaTemporal
{
    protected string $directorioRespaldo;

    protected function prepararRespaldoTemporal(): void
    {
        $this->directorioRespaldo = sys_get_temp_dir().'/respaldo-auditoria-'.uniqid();
        mkdir($this->directorioRespaldo);

        config(['logging.channels.'.AuditoriaConstants::CANAL_LOG_RESPALDO.'.path' => $this->directorioRespaldo.'/auditoria-respaldo.log']);
        Log::forgetChannel(AuditoriaConstants::CANAL_LOG_RESPALDO);
    }

    protected function limpiarRespaldoTemporal(): void
    {
        foreach (glob($this->directorioRespaldo.'/*') ?: [] as $archivo) {
            unlink($archivo);
        }

        rmdir($this->directorioRespaldo);
    }
}
