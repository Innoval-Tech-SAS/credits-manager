<?php

namespace App\Contabilidad\Support;

/**
 * Único lugar que arma el número visible de un comprobante, para que el PDF, las exportaciones contables
 * (libro diario, libro mayor, auxiliar) y sus vistas en vivo lo muestren igual. Un tipo que nunca usa
 * reinicio periódico (`ciclo` vacío) conserva el formato de siempre; uno con reinicio necesita el ciclo en
 * el número porque el mismo consecutivo se repite entre ciclos (`comprobantes` ahora es único por
 * `(tipo_comprobante_id, ciclo_numeracion, consecutivo)`, no solo por consecutivo).
 */
final class NumeroComprobante
{
    public static function formatear(string $codigoTipo, string $ciclo, int $consecutivo): string
    {
        return $ciclo === '' ? "{$codigoTipo} {$consecutivo}" : "{$codigoTipo}-{$ciclo}-{$consecutivo}";
    }
}
