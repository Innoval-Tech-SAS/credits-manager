<?php

namespace App\Core\Consecutivos\Enums;

/** Documentos que llevan un consecutivo propio por empresa, sin huecos. Facturas y notas se suman aquí en su fase. */
enum TipoDocumentoConsecutivo: string
{
    case ReciboCaja = 'recibo_caja';

    public function prefijoInicial(): string
    {
        return match ($this) {
            self::ReciboCaja => 'RC',
        };
    }
}
