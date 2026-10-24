<?php

namespace App\Exportacion\Data;

use App\Exportacion\Enums\TipoColumna;

final readonly class ColumnaExportable
{
    public function __construct(
        public string $clave,
        public string $encabezado,
        public TipoColumna $tipo = TipoColumna::Texto,
    ) {}
}
