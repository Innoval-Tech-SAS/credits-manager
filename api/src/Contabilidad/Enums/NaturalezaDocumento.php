<?php

namespace App\Contabilidad\Enums;

enum NaturalezaDocumento: string
{
    case PorPagar = 'por_pagar';
    case PorCobrar = 'por_cobrar';
}
