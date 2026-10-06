<?php

namespace App\Contabilidad\Support;

use App\Contabilidad\Enums\EstadoComprobante;

/**
 * Qué comprobantes tienen efecto en los reportes: los contabilizados y los anulados por reverso (el original se
 * contabilizó y su reverso lo compensa, así que ambos cuentan). Un borrador o revisado anulado nunca tuvo efecto y se
 * excluye: por eso se pregunta por `contabilizado_en`, que solo se llena al contabilizar.
 */
final class EfectoContable
{
    /** Condición agrupada para `where()` de Query o Eloquent Builder, sobre la tabla `comprobantes`. */
    public static function condicion(string $tabla = 'comprobantes'): \Closure
    {
        return function ($consulta) use ($tabla) {
            $consulta->where("{$tabla}.estado", EstadoComprobante::Contabilizado->value)
                ->orWhere(function ($anulados) use ($tabla) {
                    $anulados->where("{$tabla}.estado", EstadoComprobante::Anulado->value)
                        ->whereNotNull("{$tabla}.contabilizado_en");
                });
        };
    }
}
