<?php

namespace App\Contabilidad\Enums;

enum EstadoPeriodo: string
{
    case Abierto = 'abierto';
    case Cerrado = 'cerrado';
}
