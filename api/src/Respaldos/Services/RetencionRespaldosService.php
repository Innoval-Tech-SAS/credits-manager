<?php

namespace App\Respaldos\Services;

use App\Respaldos\Constants\RespaldosConstants;
use App\Respaldos\Enums\ClaseRespaldo;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;

/**
 * Borra los respaldos vencidos de cada clase. La antigüedad sale del nombre de la carpeta (fecha
 * de creación), así una copia semanal o mensual vence según su día original.
 */
class RetencionRespaldosService
{
    /** @return int número de respaldos eliminados */
    public function aplicar(CarbonImmutable $ahora): int
    {
        $eliminados = 0;

        foreach (ClaseRespaldo::cases() as $clase) {
            $carpeta = $this->carpetaDeClase($clase);

            if (! is_dir($carpeta)) {
                continue;
            }

            foreach (File::directories($carpeta) as $ruta) {
                $creado = $this->fechaDeCarpeta(basename($ruta));

                if ($creado !== null && $creado->lte($ahora->subDays($clase->diasRetencion()))) {
                    File::deleteDirectory($ruta);
                    $eliminados++;
                }
            }
        }

        return $eliminados;
    }

    public function carpetaDeClase(ClaseRespaldo $clase): string
    {
        return rtrim((string) config('respaldos.ruta'), '/').'/'.$clase->value;
    }

    private function fechaDeCarpeta(string $nombre): ?CarbonImmutable
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}_\d{6}$/', $nombre)) {
            return null;
        }

        return CarbonImmutable::createFromFormat(RespaldosConstants::FORMATO_CARPETA, $nombre, config('app.timezone'));
    }
}
