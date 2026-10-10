<?php

namespace App\Conciliacion\Enums;

enum TipoCuentaBancaria: string
{
    case Ahorros = 'ahorros';
    case Corriente = 'corriente';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Ahorros => 'Ahorros',
            self::Corriente => 'Corriente',
        };
    }
}
