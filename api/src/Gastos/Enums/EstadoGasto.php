<?php

namespace App\Gastos\Enums;

enum EstadoGasto: string
{
    case Causado = 'causado';
    case Anulado = 'anulado';
}
