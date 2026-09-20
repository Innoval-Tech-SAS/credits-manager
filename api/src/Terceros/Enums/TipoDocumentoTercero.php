<?php

namespace App\Terceros\Enums;

enum TipoDocumentoTercero: string
{
    case Cc = 'cc';
    case Ce = 'ce';
    case Pasaporte = 'pasaporte';
    case Nit = 'nit';
}
