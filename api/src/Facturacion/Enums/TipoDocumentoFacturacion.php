<?php

namespace App\Facturacion\Enums;

use App\Core\Consecutivos\Enums\TipoDocumentoConsecutivo;

enum TipoDocumentoFacturacion: string
{
    case Factura = 'factura';
    case NotaCredito = 'nota_credito';
    case NotaDebito = 'nota_debito';

    public function consecutivo(): TipoDocumentoConsecutivo
    {
        return match ($this) {
            self::Factura => TipoDocumentoConsecutivo::Factura,
            self::NotaCredito => TipoDocumentoConsecutivo::NotaCredito,
            self::NotaDebito => TipoDocumentoConsecutivo::NotaDebito,
        };
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Factura => 'Factura',
            self::NotaCredito => 'Nota crédito',
            self::NotaDebito => 'Nota débito',
        };
    }
}
