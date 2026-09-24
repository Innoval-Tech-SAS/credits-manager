<?php

namespace App\Impresion\Services;

use App\Core\Dinero\Dinero;
use App\Impresion\Constants\ImpresionConstants;
use App\Impresion\Data\ColumnaDocumento;
use App\Impresion\Enums\TipoColumnaDocumento;
use Carbon\CarbonInterface;

/** Cómo se escriben las cifras y fechas en todos los documentos: coma de miles y punto decimal, como en la plantilla de DDA. */
final class FormateadorDocumento
{
    public function dinero(Dinero $valor): string
    {
        $centavos = abs($valor->centavos());
        $enteros = number_format(intdiv($centavos, 100), 0, '.', ',');

        return ($valor->esNegativo() ? '-' : '').$enteros.'.'.str_pad((string) ($centavos % 100), 2, '0', STR_PAD_LEFT);
    }

    /** Con hora es un instante y se lleva a hora de Bogotá; sin hora es una fecha de calendario y se imprime tal cual, sin correrla de día. */
    public function fecha(CarbonInterface $fecha, bool $conHora = false): string
    {
        if (! $conHora) {
            return $fecha->format(ImpresionConstants::FORMATO_FECHA);
        }

        return $fecha->copy()->setTimezone(ImpresionConstants::ZONA_HORARIA)->format(ImpresionConstants::FORMATO_FECHA_HORA);
    }

    public function celda(mixed $valor, ColumnaDocumento $columna): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }

        return match ($columna->tipo) {
            TipoColumnaDocumento::Monetario => $this->dinero($valor instanceof Dinero ? $valor : Dinero::desdePesos($valor)),
            TipoColumnaDocumento::Fecha => $valor instanceof CarbonInterface ? $this->fecha($valor) : (string) $valor,
            TipoColumnaDocumento::Entero => number_format((float) $valor, 0, '.', ','),
            TipoColumnaDocumento::Decimal => number_format((float) $valor, 2, '.', ','),
            TipoColumnaDocumento::Porcentaje => number_format((float) $valor, 2, '.', ',').'%',
            TipoColumnaDocumento::Texto => (string) $valor,
        };
    }
}
