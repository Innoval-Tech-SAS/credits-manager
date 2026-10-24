<?php

namespace App\Contabilidad\Enums;

enum TipoNumeracionComprobante: string
{
    case Automatico = 'automatico';
    case Manual = 'manual';
}
