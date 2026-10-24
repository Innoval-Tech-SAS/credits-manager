<?php

namespace App\Contabilidad\Enums;

enum NaturalezaCuenta: string
{
    case Debito = 'debito';
    case Credito = 'credito';
}
