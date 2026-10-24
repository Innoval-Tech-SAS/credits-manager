<?php

namespace App\Facturacion\Enums;

enum MotivoNotaCredito: string
{
    case Devolucion = 'devolucion';
    case Anulacion = 'anulacion';
    case RebajaDescuento = 'rebaja_descuento';
    case AjustePrecio = 'ajuste_precio';
    case Otros = 'otros';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Devolucion => 'Devolución',
            self::Anulacion => 'Anulación',
            self::RebajaDescuento => 'Rebaja o descuento',
            self::AjustePrecio => 'Ajuste de precio',
            self::Otros => 'Otros',
        };
    }
}
