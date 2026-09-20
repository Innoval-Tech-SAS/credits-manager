<?php

namespace App\Pagos\Enums;

enum FormaPago: string
{
    case Efectivo = 'efectivo';
    case Transferencia = 'transferencia';
    case Otro = 'otro';
}
