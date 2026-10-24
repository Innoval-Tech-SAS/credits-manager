<?php

namespace App\Facturacion\Enums;

enum TipoConceptoFacturacion: string
{
    case Capital = 'capital';
    case InteresCorriente = 'interes_corriente';
    case InteresMora = 'interes_mora';
    case OtrosGastos = 'otros_gastos';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Capital => 'Capital',
            self::InteresCorriente => 'Interés corriente',
            self::InteresMora => 'Interés de mora',
            self::OtrosGastos => 'Otros gastos',
        };
    }
}
