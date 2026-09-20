<?php

namespace App\Terceros\Enums;

enum TipoTercero: string
{
    case Cliente = 'cliente';
    case Proveedor = 'proveedor';
    case Otro = 'otro';
}
