<?php

namespace App\Gastos\Enums;

enum TipoRetencion: string
{
    case ReteFuente = 'retefuente';
    case ReteIca = 'reteica';
    case ReteIva = 'reteiva';
}
