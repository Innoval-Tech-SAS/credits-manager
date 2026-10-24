<?php

namespace App\Respaldos\Enums;

enum ClaseRespaldo: string
{
    case Diario = 'diario';
    case Semanal = 'semanal';
    case Mensual = 'mensual';
    case Manual = 'manual';

    /** Días que se conserva cada respaldo de la clase. Semanal: un mes; mensual: un año. */
    public function diasRetencion(): int
    {
        return match ($this) {
            self::Diario => 7,
            self::Semanal => 31,
            self::Mensual, self::Manual => 365,
        };
    }
}
