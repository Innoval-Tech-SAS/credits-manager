<?php

namespace App\Exportacion\Enums;

enum EstadoExportacion: string
{
    case Pendiente = 'pendiente';
    case Procesando = 'procesando';
    case Completada = 'completada';
    case Fallida = 'fallida';
}
