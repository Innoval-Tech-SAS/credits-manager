<?php

namespace App\Facturacion\Enums;

enum MedioCobroFacturacion: string
{
    case Efectivo = 'efectivo';
    case Transferencia = 'transferencia';
    case Consignacion = 'consignacion';
    case Cheque = 'cheque';
    case Tarjeta = 'tarjeta';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Efectivo => 'Efectivo',
            self::Transferencia => 'Transferencia',
            self::Consignacion => 'Consignación',
            self::Cheque => 'Cheque',
            self::Tarjeta => 'Tarjeta',
        };
    }
}
