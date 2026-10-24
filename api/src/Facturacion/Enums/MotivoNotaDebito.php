<?php

namespace App\Facturacion\Enums;

enum MotivoNotaDebito: string
{
    case Intereses = 'intereses';
    case GastosPorCobrar = 'gastos_por_cobrar';
    case CambioValor = 'cambio_valor';
    case Otros = 'otros';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Intereses => 'Intereses',
            self::GastosPorCobrar => 'Gastos por cobrar',
            self::CambioValor => 'Cambio del valor',
            self::Otros => 'Otros',
        };
    }
}
