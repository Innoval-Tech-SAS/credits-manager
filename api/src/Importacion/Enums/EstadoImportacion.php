<?php

namespace App\Importacion\Enums;

enum EstadoImportacion: string
{
    case Pendiente = 'pendiente';
    case Validando = 'validando';
    case PendienteDecision = 'pendiente_decision';
    case Importando = 'importando';
    case Completada = 'completada';
    case Fallida = 'fallida';
    case Cancelada = 'cancelada';
}
