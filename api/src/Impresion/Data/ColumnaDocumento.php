<?php

namespace App\Impresion\Data;

use App\Impresion\Enums\TipoColumnaDocumento;

/** Encabezado de una columna de la tabla de líneas: lo único que cada documento decide de ella. */
final readonly class ColumnaDocumento
{
    /** @param int|null $ancho porcentaje del ancho de la tabla; las columnas sin ancho se reparten el resto */
    public function __construct(
        public string $clave,
        public string $encabezado,
        public TipoColumnaDocumento $tipo = TipoColumnaDocumento::Texto,
        public ?int $ancho = null,
    ) {}
}
