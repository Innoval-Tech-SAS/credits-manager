<?php

namespace App\Monitoreo\Services;

use App\Monitoreo\Constants\MonitoreoConstants;
use App\Respaldos\Constants\RespaldosConstants;
use App\Respaldos\Enums\ClaseRespaldo;
use App\Respaldos\Services\RetencionRespaldosService;
use App\Respaldos\Services\SubidaRespaldoExternoService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;

/**
 * Lo que hay en el volumen de respaldos, leído de las carpetas (no hay tabla de respaldos): la fecha
 * sale del nombre de la carpeta, igual que en la retención. Las carpetas temporales (con punto) se
 * ignoran: son un respaldo en curso o uno que falló.
 */
class EstadoRespaldosService
{
    private const ARCHIVOS = [
        RespaldosConstants::ARCHIVO_BASE,
        RespaldosConstants::ARCHIVO_ARCHIVOS,
        RespaldosConstants::ARCHIVO_SUMAS,
        RespaldosConstants::ARCHIVO_DATOS,
    ];

    public function __construct(
        protected RetencionRespaldosService $retencion,
        protected SubidaRespaldoExternoService $subidaExterna,
    ) {}

    public function resumen(CarbonImmutable $ahora): array
    {
        $clases = [];

        foreach (ClaseRespaldo::cases() as $clase) {
            $clases[] = [
                'clase' => $clase->value,
                'retencion_dias' => $clase->diasRetencion(),
                'respaldos' => $this->respaldosDeClase($clase),
            ];
        }

        $ultimoDiario = $clases[0]['respaldos'][0] ?? null;
        $horasDesdeUltimo = $ultimoDiario === null
            ? null
            : (int) CarbonImmutable::parse($ultimoDiario['creado_en'])->diffInHours($ahora);

        return [
            'ultimo_diario' => $ultimoDiario,
            'horas_desde_ultimo_diario' => $horasDesdeUltimo,
            'alerta' => $horasDesdeUltimo === null || $horasDesdeUltimo >= MonitoreoConstants::HORAS_ALERTA_RESPALDO || ! $ultimoDiario['completo'],
            'hora_automatica' => RespaldosConstants::HORA_AUTOMATICA,
            'externo' => [
                'configurado' => $this->subidaExterna->configurado(),
                'ultimo_resultado' => $this->subidaExterna->ultimoResultado(),
            ],
            'clases' => $clases,
        ];
    }

    /** @return list<array{carpeta: string, creado_en: string, tamano_bytes: int, completo: bool}> del más reciente al más antiguo */
    private function respaldosDeClase(ClaseRespaldo $clase): array
    {
        $carpeta = $this->retencion->carpetaDeClase($clase);

        if (! is_dir($carpeta)) {
            return [];
        }

        $respaldos = [];

        foreach (File::directories($carpeta) as $ruta) {
            $nombre = basename($ruta);

            if (! preg_match('/^\d{4}-\d{2}-\d{2}_\d{6}$/', $nombre)) {
                continue;
            }

            $respaldos[] = $this->describir($ruta, $nombre);
        }

        usort($respaldos, fn (array $a, array $b) => strcmp($b['carpeta'], $a['carpeta']));

        return array_slice($respaldos, 0, MonitoreoConstants::RESPALDOS_POR_CLASE);
    }

    private function describir(string $ruta, string $nombre): array
    {
        $tamano = 0;
        $completo = true;

        foreach (self::ARCHIVOS as $archivo) {
            $rutaArchivo = $ruta.'/'.$archivo;

            if (! is_file($rutaArchivo)) {
                $completo = false;

                continue;
            }

            $tamano += (int) filesize($rutaArchivo);
        }

        return [
            'carpeta' => $nombre,
            'creado_en' => CarbonImmutable::createFromFormat(RespaldosConstants::FORMATO_CARPETA, $nombre, config('app.timezone'))->toIso8601String(),
            'tamano_bytes' => $tamano,
            'completo' => $completo,
        ];
    }
}
