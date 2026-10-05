<?php

namespace App\Facturacion\Enums;

enum MovimientoCobro: string
{
    case Cobro = 'cobro';
    case Reversa = 'reversa';
}
