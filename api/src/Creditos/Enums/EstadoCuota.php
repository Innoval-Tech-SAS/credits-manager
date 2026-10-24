<?php

namespace App\Creditos\Enums;

enum EstadoCuota: string
{
    case Pendiente = 'pendiente';
    case Pagada = 'pagada';
    case Vencida = 'vencida';
}
