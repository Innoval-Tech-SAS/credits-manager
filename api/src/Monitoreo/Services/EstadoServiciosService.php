<?php

namespace App\Monitoreo\Services;

use App\Monitoreo\Constants\MonitoreoConstants;
use App\Monitoreo\Enums\EstadoServicio;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Estado de cada servicio del compose sin tocar el socket de Podman (que daría a la API control del
 * servidor): la base y Redis responden a una consulta, los servicios web aceptan conexión TCP y los
 * procesos de fondo dejan un latido.
 */
class EstadoServiciosService
{
    public function __construct(protected RegistroLatidos $latidos) {}

    /** @return list<array{servicio: string, estado: string, detalle: ?string, ultimo_latido: ?string}> */
    public function revisar(): array
    {
        $servicios = [
            $this->resultado('database', $this->responde(fn () => DB::select('select 1'))),
            $this->resultado('redis', $this->responde(fn () => Redis::connection()->ping())),
        ];

        foreach (config('monitoreo.servicios_tcp') as $servicio => $destino) {
            $servicios[] = $this->resultado($servicio, $this->aceptaConexion($destino['host'], (int) $destino['puerto']));
        }

        $conLatido = [
            MonitoreoConstants::SERVICIO_LISTENER,
            ...array_map(fn (string $cola) => $cola.MonitoreoConstants::SUFIJO_WORKER, MonitoreoConstants::COLAS),
            MonitoreoConstants::SERVICIO_SCHEDULER,
            MonitoreoConstants::SERVICIO_MONITOR,
        ];

        foreach ($conLatido as $servicio) {
            $servicios[] = $this->desdeLatido($servicio);
        }

        return $servicios;
    }

    private function desdeLatido(string $servicio): array
    {
        $latido = $this->latidos->leer($servicio);

        if ($latido === null) {
            return $this->resultado($servicio, false, 'Sin latido reciente');
        }

        return [
            'servicio' => $servicio,
            'estado' => EstadoServicio::tryFrom($latido['estado'])?->value ?? EstadoServicio::Activo->value,
            'detalle' => $latido['detalle'],
            'ultimo_latido' => $latido['registrado_en'],
        ];
    }

    private function resultado(string $servicio, bool $activo, ?string $detalle = null): array
    {
        return [
            'servicio' => $servicio,
            'estado' => ($activo ? EstadoServicio::Activo : EstadoServicio::Caido)->value,
            'detalle' => $activo ? null : ($detalle ?? 'No responde'),
            'ultimo_latido' => null,
        ];
    }

    private function responde(callable $consulta): bool
    {
        try {
            $consulta();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function aceptaConexion(string $host, int $puerto): bool
    {
        $conexion = @fsockopen($host, $puerto, $codigo, $mensaje, MonitoreoConstants::TIEMPO_ESPERA_TCP_SEGUNDOS);

        if ($conexion === false) {
            return false;
        }

        fclose($conexion);

        return true;
    }
}
