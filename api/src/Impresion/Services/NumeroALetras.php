<?php

namespace App\Impresion\Services;

use App\Core\Dinero\Dinero;
use InvalidArgumentException;

/** Valor en letras de un monto en pesos colombianos, como se imprime en facturas y recibos. */
final class NumeroALetras
{
    private const MAXIMO = 999_999_999_999;

    private const UNIDADES = [
        '', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve', 'diez', 'once', 'doce', 'trece', 'catorce', 'quince',
        'dieciséis', 'diecisiete', 'dieciocho', 'diecinueve', 'veinte', 'veintiuno', 'veintidós', 'veintitrés', 'veinticuatro', 'veinticinco',
        'veintiséis', 'veintisiete', 'veintiocho', 'veintinueve',
    ];

    private const DECENAS = [3 => 'treinta', 4 => 'cuarenta', 5 => 'cincuenta', 6 => 'sesenta', 7 => 'setenta', 8 => 'ochenta', 9 => 'noventa'];

    private const CENTENAS = [
        1 => 'ciento', 2 => 'doscientos', 3 => 'trescientos', 4 => 'cuatrocientos', 5 => 'quinientos',
        6 => 'seiscientos', 7 => 'setecientos', 8 => 'ochocientos', 9 => 'novecientos',
    ];

    /** "Un millón doscientos veintidós mil trescientos ochenta pesos m/cte"; con centavos: "… pesos con 50/100 m/cte". */
    public static function pesos(Dinero $valor): string
    {
        $centavos = abs($valor->centavos());
        $enteros = intdiv($centavos, 100);
        $fraccion = $centavos % 100;

        if ($enteros > self::MAXIMO) {
            throw new InvalidArgumentException('El monto supera lo que se puede escribir en letras.');
        }

        $texto = $enteros === 0 ? 'cero' : self::entero($enteros);

        if ($enteros >= 1_000_000 && $enteros % 1_000_000 === 0) {
            $texto .= ' de';
        }

        $texto .= $enteros === 1 ? ' peso' : ' pesos';

        if ($fraccion > 0) {
            $texto .= sprintf(' con %02d/100', $fraccion);
        }

        $texto .= ' m/cte';

        if ($valor->esNegativo()) {
            $texto = 'menos '.$texto;
        }

        return mb_strtoupper(mb_substr($texto, 0, 1)).mb_substr($texto, 1);
    }

    private static function entero(int $n): string
    {
        // Siempre "un": el número precede a "pesos" (veintiún pesos, ciento un pesos).
        if ($n < 1_000_000) {
            return self::menorAUnMillon($n, true);
        }

        $millones = intdiv($n, 1_000_000);
        $resto = $n % 1_000_000;

        $texto = $millones === 1 ? 'un millón' : self::menorAUnMillon($millones, true).' millones';

        return $resto > 0 ? $texto.' '.self::menorAUnMillon($resto, true) : $texto;
    }

    /** @param bool $apocopar "un" en lugar de "uno" al final ("veintiún millones", "treinta y un mil") */
    private static function menorAUnMillon(int $n, bool $apocopar): string
    {
        $miles = intdiv($n, 1000);
        $resto = $n % 1000;
        $partes = [];

        if ($miles > 0) {
            $partes[] = $miles === 1 ? 'mil' : self::menorAMil($miles, true).' mil';
        }

        if ($resto > 0) {
            $partes[] = self::menorAMil($resto, $apocopar);
        }

        return implode(' ', $partes);
    }

    private static function menorAMil(int $n, bool $apocopar): string
    {
        if ($n === 100) {
            return 'cien';
        }

        $centenas = intdiv($n, 100);
        $resto = $n % 100;
        $partes = [];

        if ($centenas > 0) {
            $partes[] = self::CENTENAS[$centenas];
        }

        if ($resto > 0) {
            $partes[] = self::decenas($resto, $apocopar);
        }

        return implode(' ', $partes);
    }

    private static function decenas(int $n, bool $apocopar): string
    {
        if ($n < 30) {
            return self::apocopar(self::UNIDADES[$n], $apocopar);
        }

        $decena = self::DECENAS[intdiv($n, 10)];
        $unidad = $n % 10;

        return $unidad === 0 ? $decena : $decena.' y '.self::apocopar(self::UNIDADES[$unidad], $apocopar);
    }

    private static function apocopar(string $palabra, bool $apocopar): string
    {
        if (! $apocopar) {
            return $palabra;
        }

        return match ($palabra) {
            'uno' => 'un',
            'veintiuno' => 'veintiún',
            default => $palabra,
        };
    }
}
