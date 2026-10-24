<?php

namespace App\Creditos\Enums;

enum EstadoCredito: string
{
    case Solicitado = 'solicitado';
    case Aprobado = 'aprobado';
    case Desembolsado = 'desembolsado';
    case Pagado = 'pagado';
    case Cancelado = 'cancelado';
    case Refinanciado = 'refinanciado';
}
