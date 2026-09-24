<?php

namespace App\Impresion\Data;

use App\Core\Dinero\Dinero;

final readonly class TotalDocumento
{
    public function __construct(
        public string $etiqueta,
        public Dinero $valor,
    ) {}
}
