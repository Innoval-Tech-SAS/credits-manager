<?php

namespace App\Causacion\Enums;

enum TipoRetencion: string
{
    case ReteFuente = 'retefuente';
    case ReteIca = 'reteica';
    case ReteIva = 'reteiva';
}
