<?php

namespace App\Contabilidad\Enums;

enum EstadoDocumentoTercero: string
{
    case Pendiente = 'pendiente';
    case Cancelado = 'cancelado';
}
