<?php

namespace App\Impresion\Enums;

enum TipoColumnaDocumento: string
{
    case Texto = 'texto';
    case Entero = 'entero';
    case Decimal = 'decimal';
    case Monetario = 'monetario';
    case Porcentaje = 'porcentaje';
    case Fecha = 'fecha';

    /** Las cifras se alinean a la derecha y lo demás a la izquierda, igual en todos los documentos. */
    public function alineacion(): string
    {
        return match ($this) {
            self::Entero, self::Decimal, self::Monetario, self::Porcentaje => 'right',
            self::Texto, self::Fecha => 'left',
        };
    }
}
