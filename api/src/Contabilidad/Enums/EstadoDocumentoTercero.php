<?php

namespace App\Contabilidad\Enums;

enum EstadoDocumentoTercero: string
{
    case Pendiente = 'pendiente';
    case AbonadoParcial = 'abonado_parcial';
    case Cancelado = 'cancelado';
    case Anulado = 'anulado';
}
