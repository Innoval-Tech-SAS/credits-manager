<?php

namespace App\Importacion\Data;

use RuntimeException;

/** Motivo de rechazo de una fila: texto legible para el usuario, con la columna que lo causa cuando se conoce. */
final class FilaRechazada extends RuntimeException
{
    public function __construct(string $motivo, public readonly ?string $columna = null)
    {
        parent::__construct($motivo);
    }
}
