<?php

namespace App\Exportacion\Enums;

enum TipoColumna: string
{
    case Texto = 'texto';
    case Entero = 'entero';
    case Decimal = 'decimal';
    case Monetario = 'monetario';
    case Porcentaje = 'porcentaje';
    case Fecha = 'fecha';
    case FechaHora = 'fecha_hora';
    case Booleano = 'booleano';
}
