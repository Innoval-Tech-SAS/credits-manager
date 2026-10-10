<?php

namespace App\Conciliacion\Constants;

class ConciliacionConstants
{
    public const DISCO = 'conciliacion';

    /** Conexión de cola explícita, igual que exportaciones/importaciones: no depende de QUEUE_CONNECTION. */
    public const CONEXION_COLA = 'redis';

    public const COLA = 'conciliacion';

    public const TIMEOUT_JOB_SEGUNDOS = 300;
}
