<?php

namespace App\Conciliacion\Lectura\Data;

use App\Core\Dinero\Dinero;

final class MovimientoLeido
{
    public function __construct(
        public readonly string $fecha,
        public readonly string $descripcion,
        public readonly ?string $sucursal,
        public readonly ?string $documento,
        public readonly Dinero $valor,
        public readonly Dinero $saldo,
        public readonly int $orden,
    ) {}
}
