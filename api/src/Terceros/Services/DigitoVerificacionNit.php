<?php

namespace App\Terceros\Services;

/** Dígito de verificación del NIT (módulo 11 de la DIAN). */
class DigitoVerificacionNit
{
    private const PESOS = [3, 7, 13, 17, 19, 23, 29, 37, 41, 43, 47, 53, 59, 67, 71];

    public static function calcular(string $nit): int
    {
        $digitos = array_reverse(str_split(preg_replace('/\D/', '', $nit)));

        $suma = 0;
        foreach ($digitos as $posicion => $digito) {
            $suma += (int) $digito * (self::PESOS[$posicion] ?? 0);
        }

        $residuo = $suma % 11;

        return $residuo > 1 ? 11 - $residuo : $residuo;
    }
}
