<?php

namespace App\Contabilidad\Enums;

enum EstadoSolicitudReapertura: string
{
    case Pendiente = 'pendiente';
    case Autorizada = 'autorizada';
    case Rechazada = 'rechazada';
}
