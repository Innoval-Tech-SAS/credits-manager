<?php

namespace App\Conciliacion\Lectura\Data;

use App\Core\Dinero\Dinero;

final class ResumenExtracto
{
    public function __construct(
        public readonly Dinero $saldoAnterior,
        public readonly Dinero $totalAbonos,
        public readonly Dinero $totalCargos,
        public readonly Dinero $saldoActual,
    ) {}
}
