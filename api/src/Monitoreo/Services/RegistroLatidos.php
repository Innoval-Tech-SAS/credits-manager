<?php

namespace App\Monitoreo\Services;

use App\Monitoreo\Constants\MonitoreoConstants;
use App\Monitoreo\Enums\EstadoServicio;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Cada proceso de larga duración (workers, listener, scheduler) deja aquí un latido con vencimiento.
 * Nunca lanza: un fallo de Redis no puede tumbar al worker que solo quería avisar que sigue vivo.
 */
class RegistroLatidos
{
    /** @var array<string, array{0: float, 1: EstadoServicio}> último latido escrito por este proceso, por servicio */
    private array $escritos = [];

    /** Un cambio de estado se escribe al instante; repetir el mismo estado, como mucho cada pocos segundos. */
    public function registrar(string $servicio, EstadoServicio $estado, ?string $detalle = null, ?int $vigencia = null): void
    {
        $ahora = microtime(true);
        [$ultimo, $estadoAnterior] = $this->escritos[$servicio] ?? [0.0, null];

        if ($estado === $estadoAnterior && $ahora - $ultimo < MonitoreoConstants::INTERVALO_LATIDO_SEGUNDOS) {
            return;
        }

        $vigencia ??= $estado === EstadoServicio::Ocupado
            ? MonitoreoConstants::VIGENCIA_LATIDO_OCUPADO_SEGUNDOS
            : MonitoreoConstants::VIGENCIA_LATIDO_SEGUNDOS;

        try {
            $this->almacen()->put(MonitoreoConstants::PREFIJO_LATIDO.$servicio, [
                'estado' => $estado->value,
                'detalle' => $detalle,
                'registrado_en' => now()->toIso8601String(),
            ], $vigencia);

            $this->escritos[$servicio] = [$ahora, $estado];
        } catch (Throwable) {
        }
    }

    /** @return array{estado: string, detalle: ?string, registrado_en: string}|null */
    public function leer(string $servicio): ?array
    {
        try {
            $latido = $this->almacen()->get(MonitoreoConstants::PREFIJO_LATIDO.$servicio);
        } catch (Throwable) {
            return null;
        }

        return is_array($latido) ? $latido : null;
    }

    private function almacen(): Repository
    {
        return Cache::store(config('monitoreo.almacen'));
    }
}
