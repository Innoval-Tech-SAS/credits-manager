<?php

namespace App\Monitoreo\Services;

use App\Auditoria\Constants\AuditoriaConstants;
use App\Monitoreo\Constants\MonitoreoConstants;
use App\Monitoreo\Repositories\TrabajoFallidoRepository;
use App\Redis\Contracts\RedisClienteInterface;
use Illuminate\Queue\RedisQueue;
use Illuminate\Support\Facades\Queue;
use Throwable;

/** Trabajos por cola (en Redis) y fallidos (en MySQL), más el atraso del stream de auditoría. */
class EstadoColasService
{
    public function __construct(
        protected TrabajoFallidoRepository $fallidos,
        protected RedisClienteInterface $redis,
    ) {}

    /** @return list<array{cola: string, pendientes: ?int, programados: ?int, en_proceso: ?int, fallidos: ?int}> */
    public function colas(): array
    {
        $cola = $this->colaRedis();
        $fallidos = $this->fallidosPorCola();

        return array_map(fn (string $nombre) => [
            'cola' => $nombre,
            'pendientes' => $this->medir(fn () => $cola?->pendingSize($nombre)),
            'programados' => $this->medir(fn () => $cola?->delayedSize($nombre)),
            'en_proceso' => $this->medir(fn () => $cola?->reservedSize($nombre)),
            'fallidos' => $fallidos === null ? null : ($fallidos[$nombre] ?? 0),
        ], MonitoreoConstants::COLAS);
    }

    /** @return array{en_stream: ?int, pendientes: ?int} eventos que el listener aún no pasa a la bitácora */
    public function auditoria(): array
    {
        return [
            'en_stream' => $this->medir(fn () => $this->redis->longitud(AuditoriaConstants::STREAM)),
            'pendientes' => $this->medir(fn () => $this->redis->pendientes(AuditoriaConstants::STREAM, AuditoriaConstants::GRUPO)),
        ];
    }

    private function colaRedis(): ?RedisQueue
    {
        try {
            $cola = Queue::connection('redis');
        } catch (Throwable) {
            return null;
        }

        return $cola instanceof RedisQueue ? $cola : null;
    }

    private function fallidosPorCola(): ?array
    {
        try {
            return $this->fallidos->contarPorCola();
        } catch (Throwable) {
            return null;
        }
    }

    private function medir(callable $lectura): ?int
    {
        try {
            $valor = $lectura();
        } catch (Throwable) {
            return null;
        }

        return $valor === null ? null : (int) $valor;
    }
}
