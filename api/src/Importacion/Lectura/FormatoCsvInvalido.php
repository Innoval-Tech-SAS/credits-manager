<?php

namespace App\Importacion\Lectura;

use RuntimeException;

/** El archivo no trae los encabezados obligatorios de su hoja. */
final class FormatoCsvInvalido extends RuntimeException
{
    /** @param list<string> $faltantes */
    public function __construct(public readonly array $faltantes)
    {
        parent::__construct('Faltan columnas obligatorias: '.implode(', ', $faltantes).'.');
    }
}
