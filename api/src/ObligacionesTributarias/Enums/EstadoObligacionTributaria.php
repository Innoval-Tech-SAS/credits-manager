<?php

namespace App\ObligacionesTributarias\Enums;

enum EstadoObligacionTributaria: string
{
    case Pendiente = 'pendiente';
    case Cumplida = 'cumplida';
    case NoAplica = 'no_aplica';
}
