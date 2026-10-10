<?php

namespace App\Conciliacion\Enums;

enum EstadoExtracto: string
{
    /** Transitorio: el job todavía está leyendo y validando el archivo. */
    case Cargando = 'cargando';
    case Cargado = 'cargado';
    case Rechazado = 'rechazado';
    case Cerrado = 'cerrado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Cargando => 'Cargando',
            self::Cargado => 'Cargado',
            self::Rechazado => 'Rechazado',
            self::Cerrado => 'Cerrado',
        };
    }
}
