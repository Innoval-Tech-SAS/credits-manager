<?php

namespace App\Cartera\Constants;

class CarteraConstants
{
    /** Rangos de altura de mora (estándar de la Superintendencia Financiera): clave => último día del rango, `null` = sin tope. */
    public const RANGOS_MORA = [
        '1_30' => 30,
        '31_60' => 60,
        '61_90' => 90,
        'mas_90' => null,
    ];
}
