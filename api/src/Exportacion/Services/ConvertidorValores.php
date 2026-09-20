<?php

namespace App\Exportacion\Services;

use App\Core\Dinero\Dinero;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * Único lugar donde un valor de dominio (Dinero, cadena decimal, fecha) pasa a lo que escribe un
 * archivo. Los escritores lo comparten para que XLSX y CSV traten igual cada tipo de columna.
 */
class ConvertidorValores
{
    private const INICIOS_DE_FORMULA = ['=', '+', '-', '@', "\t", "\r"];

    /** Texto libre de usuarios (la bitácora lo contiene): un valor que Excel pueda leer como fórmula se antepone con apóstrofo. */
    public function neutralizar(string $texto): string
    {
        $inicio = ltrim($texto)[0] ?? '';

        return $inicio !== '' && (in_array($texto[0], self::INICIOS_DE_FORMULA, true) || in_array($inicio, self::INICIOS_DE_FORMULA, true))
            ? "'".$texto
            : $texto;
    }

    public function texto(mixed $valor): ?string
    {
        return match (true) {
            $valor === null => null,
            is_bool($valor) => $valor ? 'true' : 'false',
            is_scalar($valor) || $valor instanceof \Stringable => (string) $valor,
            default => json_encode($valor, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?: null,
        };
    }

    public function entero(mixed $valor): ?int
    {
        return $valor === null || $valor === '' ? null : (int) $valor;
    }

    public function decimal(mixed $valor): ?float
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        if (! is_numeric($valor)) {
            throw new InvalidArgumentException('Valor decimal no numérico en la exportación.');
        }

        return (float) $valor;
    }

    public function monetarioComoFloat(mixed $valor): ?float
    {
        return $this->dinero($valor)?->aFloat();
    }

    public function monetarioComoTexto(mixed $valor): ?string
    {
        return $this->dinero($valor)?->aDecimalString();
    }

    public function fecha(mixed $valor): ?CarbonImmutable
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        $zona = config('app.timezone');

        return $valor instanceof DateTimeInterface
            ? CarbonImmutable::instance($valor)->setTimezone($zona)
            : CarbonImmutable::parse((string) $valor, $zona);
    }

    public function booleano(mixed $valor): ?bool
    {
        return $valor === null ? null : (bool) $valor;
    }

    private function dinero(mixed $valor): ?Dinero
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        if ($valor instanceof Dinero) {
            return $valor;
        }

        if (! is_numeric($valor)) {
            throw new InvalidArgumentException('Valor monetario no numérico en la exportación.');
        }

        return Dinero::desdePesos($valor);
    }
}
