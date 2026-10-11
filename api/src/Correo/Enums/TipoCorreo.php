<?php

namespace App\Correo\Enums;

enum TipoCorreo: string
{
    case Cobranza = 'cobranza';
    case Facturacion = 'facturacion';
}
