<?php

namespace App\Importacion\Data;

use RuntimeException;

/** La fila necesita un empleador que aún no existe: la decisión del usuario define si se crea o se rechaza. */
final class EmpleadorPendiente extends RuntimeException
{
    public function __construct(public readonly string $nombre)
    {
        parent::__construct("Empleador «{$nombre}» no existe.");
    }
}
