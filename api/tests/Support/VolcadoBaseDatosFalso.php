<?php

namespace Tests\Support;

use App\Respaldos\Services\VolcadoBaseDatosService;

/** Sustituye el volcado real (mariadb-dump) en la suite: escribe un SQL comprimido de prueba. */
class VolcadoBaseDatosFalso extends VolcadoBaseDatosService
{
    public function volcar(string $destinoGz): void
    {
        $gz = gzopen($destinoGz, 'wb9');
        gzwrite($gz, "-- volcado de prueba\n");
        gzclose($gz);
    }
}
