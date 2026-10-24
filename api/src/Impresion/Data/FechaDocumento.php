<?php

namespace App\Impresion\Data;

use Carbon\CarbonInterface;

/** Una fila de la tabla de fechas del encabezado (generación, expedición, vencimiento, corte…). */
final readonly class FechaDocumento
{
    public function __construct(
        public string $etiqueta,
        public CarbonInterface $valor,
        public bool $conHora = false,
    ) {}
}
