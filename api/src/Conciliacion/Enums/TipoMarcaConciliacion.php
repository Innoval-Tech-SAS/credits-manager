<?php

namespace App\Conciliacion\Enums;

/** Se usa desde la Fase 6.2: el modelo y la tabla nacen con el resto del esquema de la Fase 6.1. */
enum TipoMarcaConciliacion: string
{
    case Conciliado = 'conciliado';
    case Puente = 'puente';
    case Reversa = 'reversa';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Conciliado => 'Conciliado con el libro',
            self::Puente => 'Enviado a cuenta puente',
            self::Reversa => 'Reversa',
        };
    }
}
