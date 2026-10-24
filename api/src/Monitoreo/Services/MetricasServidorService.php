<?php

namespace App\Monitoreo\Services;

use App\Monitoreo\Constants\MonitoreoConstants;

/**
 * Métricas del servidor leídas de /proc (que en el contenedor muestra el servidor completo) y del
 * disco donde viven los volúmenes. Una lectura que falta queda en null: el panel la muestra vacía.
 */
class MetricasServidorService
{
    public function medir(): array
    {
        return [
            'cpu' => $this->cpu(),
            'memoria' => $this->memoria(),
            'carga' => $this->carga(),
            'disco' => $this->disco(),
            'red' => $this->red(),
            'encendido_segundos' => $this->encendido(),
        ];
    }

    /** @return array{porcentaje: float, nucleos: int}|null */
    private function cpu(): ?array
    {
        $antes = $this->tiemposCpu();
        usleep(MonitoreoConstants::MUESTREO_CPU_MICROSEGUNDOS);
        $despues = $this->tiemposCpu();

        if ($antes === null || $despues === null) {
            return null;
        }

        $total = $despues['total'] - $antes['total'];
        $inactivo = $despues['inactivo'] - $antes['inactivo'];

        return [
            'porcentaje' => $total > 0 ? round(100 * ($total - $inactivo) / $total, 1) : 0.0,
            'nucleos' => $this->nucleos(),
        ];
    }

    /** Primera línea de /proc/stat: user nice system idle iowait irq softirq steal ... (idle + iowait = inactivo). */
    private function tiemposCpu(): ?array
    {
        $contenido = $this->leer('stat');

        if ($contenido === null || ! preg_match('/^cpu\s+(.+)$/m', $contenido, $coincidencia)) {
            return null;
        }

        $valores = array_map('intval', preg_split('/\s+/', trim($coincidencia[1])));

        return [
            'total' => array_sum(array_slice($valores, 0, 8)),
            'inactivo' => ($valores[3] ?? 0) + ($valores[4] ?? 0),
        ];
    }

    private function nucleos(): int
    {
        $contenido = $this->leer('stat') ?? '';

        return max(1, preg_match_all('/^cpu\d+\s/m', $contenido));
    }

    /** @return array{total_bytes: int, usada_bytes: int, porcentaje: float, swap_total_bytes: int, swap_usada_bytes: int}|null */
    private function memoria(): ?array
    {
        $contenido = $this->leer('meminfo');

        if ($contenido === null) {
            return null;
        }

        preg_match_all('/^(\w+):\s+(\d+)\s*kB$/m', $contenido, $coincidencias);
        $kb = array_combine($coincidencias[1], array_map('intval', $coincidencias[2]));

        if (! isset($kb['MemTotal'], $kb['MemAvailable']) || $kb['MemTotal'] === 0) {
            return null;
        }

        $usada = $kb['MemTotal'] - $kb['MemAvailable'];

        return [
            'total_bytes' => $kb['MemTotal'] * 1024,
            'usada_bytes' => $usada * 1024,
            'porcentaje' => round(100 * $usada / $kb['MemTotal'], 1),
            'swap_total_bytes' => ($kb['SwapTotal'] ?? 0) * 1024,
            'swap_usada_bytes' => (($kb['SwapTotal'] ?? 0) - ($kb['SwapFree'] ?? 0)) * 1024,
        ];
    }

    /** @return list<float>|null carga promedio a 1, 5 y 15 minutos */
    private function carga(): ?array
    {
        $contenido = $this->leer('loadavg');

        if ($contenido === null) {
            return null;
        }

        return array_map('floatval', array_slice(preg_split('/\s+/', trim($contenido)), 0, 3));
    }

    /** Los volúmenes del compose viven en el disco del servidor: el de respaldos lo representa. */
    private function disco(): ?array
    {
        $ruta = (string) config('respaldos.ruta');
        $total = is_dir($ruta) ? @disk_total_space($ruta) : false;
        $libre = is_dir($ruta) ? @disk_free_space($ruta) : false;

        if ($total === false || $libre === false || $total <= 0) {
            return null;
        }

        return [
            'total_bytes' => (int) $total,
            'usado_bytes' => (int) ($total - $libre),
            'porcentaje' => round(100 * ($total - $libre) / $total, 1),
        ];
    }

    /**
     * Contador acumulado desde que la interfaz arrancó (no una tasa): el cliente calcula bytes/segundo
     * comparando dos muestras consecutivas, igual que ya hace con el histórico de CPU/memoria.
     *
     * @return array{rx_bytes: int, tx_bytes: int}|null suma de todas las interfaces, sin contar loopback
     */
    private function red(): ?array
    {
        $contenido = $this->leer('net/dev');

        if ($contenido === null) {
            return null;
        }

        $rxBytes = 0;
        $txBytes = 0;
        $encontrada = false;

        foreach (explode("\n", $contenido) as $linea) {
            if (! preg_match('/^\s*([^:\s]+):\s*(.+)$/', $linea, $coincidencia) || $coincidencia[1] === 'lo') {
                continue;
            }

            $valores = array_map('intval', preg_split('/\s+/', trim($coincidencia[2])));

            if (count($valores) < 9) {
                continue;
            }

            $rxBytes += $valores[0];
            $txBytes += $valores[8];
            $encontrada = true;
        }

        return $encontrada ? ['rx_bytes' => $rxBytes, 'tx_bytes' => $txBytes] : null;
    }

    private function encendido(): ?int
    {
        $contenido = $this->leer('uptime');

        return $contenido === null ? null : (int) floatval($contenido);
    }

    private function leer(string $archivo): ?string
    {
        $ruta = rtrim((string) config('monitoreo.ruta_proc'), '/').'/'.$archivo;
        $contenido = is_readable($ruta) ? @file_get_contents($ruta) : false;

        return $contenido === false ? null : $contenido;
    }
}
