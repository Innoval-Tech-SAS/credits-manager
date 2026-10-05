<?php

namespace App\Core\Consecutivos\Enums;

/** Documentos que llevan un consecutivo propio por empresa, sin huecos. */
enum TipoDocumentoConsecutivo: string
{
    case ReciboCaja = 'recibo_caja';
    case Factura = 'factura';
    case NotaCredito = 'nota_credito';
    case NotaDebito = 'nota_debito';

    public function prefijoInicial(): string
    {
        return match ($this) {
            self::ReciboCaja => 'RC',
            self::Factura => 'F',
            self::NotaCredito => 'NC',
            self::NotaDebito => 'ND',
        };
    }
}
