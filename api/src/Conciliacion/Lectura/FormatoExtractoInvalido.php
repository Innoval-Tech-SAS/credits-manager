<?php

namespace App\Conciliacion\Lectura;

use RuntimeException;

/** El archivo no trae la fila de encabezados (`FECHA`, `DESCRIPCIÓN`...) del formato del banco. */
final class FormatoExtractoInvalido extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('El archivo no tiene el formato esperado del extracto bancario.');
    }
}
