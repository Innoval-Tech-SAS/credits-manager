<?php

namespace App\Auditoria\Services;

use App\Auditoria\Constants\AuditoriaConstants;
use App\Auditoria\Data\EventoAuditoria;
use App\Auditoria\Enums\MotivoRespaldo;
use Illuminate\Support\Facades\Log;
use SplFileObject;
use Throwable;

/**
 * Log de respaldo de auditoría: adonde va todo evento que no llegó a Redis o no se pudo
 * insertar. Una línea JSON por entrada (canal `auditoria_respaldo`), que el panel de
 * recuperación relee para reinyectar los eventos al stream.
 */
class RespaldoAuditoriaService
{
    public function guardar(MotivoRespaldo $motivo, ?EventoAuditoria $evento, string $error, ?array $crudo = null): void
    {
        $contexto = [
            'motivo_fallo' => $motivo->value,
            'error' => mb_substr($error, 0, 1000),
            'evento_id' => $evento?->eventoId ?? ($crudo['evento_id'] ?? null),
            'evento' => $evento?->aArray(),
            'payload_crudo' => $evento === null ? $crudo : null,
        ];

        try {
            Log::channel(AuditoriaConstants::CANAL_LOG_RESPALDO)->critical('Evento de auditoría en respaldo', $contexto);
        } catch (Throwable $excepcion) {
            error_log('auditoria_respaldo no se pudo escribir ('.$excepcion->getMessage().'): '.json_encode($contexto, JSON_UNESCAPED_UNICODE));
        }
    }

    /**
     * Entradas del log, de la más reciente a la más antigua.
     *
     * @return list<array{registrado_en: string, evento_id: ?string, motivo_fallo: string, error: string, evento: ?array, payload_crudo: ?array}>
     */
    public function entradas(): array
    {
        $ruta = (string) config('logging.channels.'.AuditoriaConstants::CANAL_LOG_RESPALDO.'.path');
        $archivos = glob(dirname($ruta).'/'.pathinfo($ruta, PATHINFO_FILENAME).'-*.log') ?: [];
        rsort($archivos);

        $entradas = [];

        foreach ($archivos as $archivo) {
            $lineas = [];
            $lector = new SplFileObject($archivo, 'r');

            while (! $lector->eof()) {
                $linea = trim((string) $lector->fgets());

                if ($linea !== '') {
                    $lineas[] = $linea;
                }
            }

            foreach (array_reverse($lineas) as $linea) {
                $registro = json_decode($linea, true);
                $contexto = is_array($registro) ? ($registro['context'] ?? null) : null;

                if (! is_array($contexto) || ! isset($contexto['motivo_fallo'])) {
                    continue;
                }

                $entradas[] = [
                    'registrado_en' => (string) ($registro['datetime'] ?? ''),
                    'evento_id' => $contexto['evento_id'] ?? null,
                    'motivo_fallo' => (string) $contexto['motivo_fallo'],
                    'error' => (string) ($contexto['error'] ?? ''),
                    'evento' => $contexto['evento'] ?? null,
                    'payload_crudo' => $contexto['payload_crudo'] ?? null,
                ];
            }
        }

        return $entradas;
    }
}
