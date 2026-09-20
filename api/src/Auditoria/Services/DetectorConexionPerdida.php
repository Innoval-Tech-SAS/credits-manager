<?php

namespace App\Auditoria\Services;

use Illuminate\Database\DetectsLostConnections;
use Throwable;

class DetectorConexionPerdida
{
    use DetectsLostConnections;

    public function detecta(Throwable $excepcion): bool
    {
        return $this->causedByLostConnection($excepcion);
    }
}
