<?php

namespace App\Conciliacion\Enums;

/** Conciliado/Puente se asignan desde la Fase 6.2; todo movimiento nace Pendiente. */
enum EstadoMovimientoExtracto: string
{
    case Pendiente = 'pendiente';
    case Conciliado = 'conciliado';
    case Puente = 'puente';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Conciliado => 'Conciliado',
            self::Puente => 'Enviado a cuenta puente',
        };
    }
}
