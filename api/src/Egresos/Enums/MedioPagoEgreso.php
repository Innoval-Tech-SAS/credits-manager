<?php

namespace App\Egresos\Enums;

enum MedioPagoEgreso: string
{
    case Efectivo = 'efectivo';
    case Transferencia = 'transferencia';
    case Consignacion = 'consignacion';
    case Cheque = 'cheque';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Efectivo => 'Efectivo',
            self::Transferencia => 'Transferencia',
            self::Consignacion => 'Consignación',
            self::Cheque => 'Cheque',
        };
    }
}
