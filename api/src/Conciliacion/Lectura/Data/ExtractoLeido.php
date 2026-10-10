<?php

namespace App\Conciliacion\Lectura\Data;

final class ExtractoLeido
{
    /** @param list<MovimientoLeido> $movimientos */
    public function __construct(
        public readonly ResumenExtracto $resumen,
        public readonly array $movimientos,
    ) {}
}
