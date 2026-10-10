<?php

namespace App\Facturacion\Constants;

class FacturacionConstants
{
    /**
     * Valores por defecto de los parámetros de cada empresa. `nota_debito_tope` en centavos
     * (1.000.000,00 COP): límite por defecto, configurable por empresa.
     */
    public const PARAMETROS_DEFECTO = [
        'nota_debito_tope' => 100_000_000,
    ];
}
