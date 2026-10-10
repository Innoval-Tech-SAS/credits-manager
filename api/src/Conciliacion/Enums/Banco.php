<?php

namespace App\Conciliacion\Enums;

enum Banco: string
{
    case Bancolombia = 'bancolombia';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Bancolombia => 'Bancolombia',
        };
    }
}
