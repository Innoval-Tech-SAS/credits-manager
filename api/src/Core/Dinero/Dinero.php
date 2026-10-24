<?php

namespace App\Core\Dinero;

use InvalidArgumentException;
use JsonSerializable;

/**
 * Representa un valor monetario en pesos colombianos (COP) como centavos
 * enteros — nunca como float/decimal. Inmutable: toda operación devuelve una
 * nueva instancia. Serializa a JSON como el mismo string decimal de 2 cifras
 * que producía antes el cast `decimal:2` (ej. "12345.66"), para no romper el
 * contrato de la API.
 */
final class Dinero implements JsonSerializable
{
    private function __construct(private readonly int $centavos) {}

    public static function desdeCentavos(int $centavos): self
    {
        return new self($centavos);
    }

    /** Acepta int, float o string decimal (ej. 1000, 1000.5, "1000.50"). */
    public static function desdePesos(int|float|string $pesos): self
    {
        return new self(self::centavosDesdePesos($pesos));
    }

    public static function cero(): self
    {
        return new self(0);
    }

    public static function sumarVarios(self ...$valores): self
    {
        return new self(array_sum(array_map(fn (self $v) => $v->centavos, $valores)));
    }

    public function centavos(): int
    {
        return $this->centavos;
    }

    /** Representación canónica para persistencia/JSON: 2 decimales exactos, sin locale. */
    public function aDecimalString(): string
    {
        $negativo = $this->centavos < 0;
        $absoluto = abs($this->centavos);

        return ($negativo ? '-' : '').intdiv($absoluto, 100).'.'.str_pad((string) ($absoluto % 100), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Solo para operar contra magnitudes no monetarias (tasas, porcentajes) que sí
     * son float en este dominio — nunca usar el resultado para comparar o persistir
     * dinero directamente, siempre volver a construir un `Dinero` con el resultado.
     */
    public function aFloat(): float
    {
        return $this->centavos / 100;
    }

    public function sumar(self $otro): self
    {
        return new self($this->centavos + $otro->centavos);
    }

    public function restar(self $otro): self
    {
        return new self($this->centavos - $otro->centavos);
    }

    /** Multiplica por una tasa/porcentaje float y redondea UNA sola vez a centavo (mitad arriba). */
    public function multiplicarPorTasa(float $tasa): self
    {
        return new self((int) round($this->centavos * $tasa));
    }

    /** Reparte en $partes partes iguales, redondeando hacia abajo — el residuo lo maneja el llamador. */
    public function dividirEntre(int $partes): self
    {
        if ($partes <= 0) {
            throw new InvalidArgumentException('El divisor debe ser mayor que cero.');
        }

        return new self(intdiv($this->centavos, $partes));
    }

    public function negar(): self
    {
        return new self(-$this->centavos);
    }

    public function absoluto(): self
    {
        return new self(abs($this->centavos));
    }

    public function esCero(): bool
    {
        return $this->centavos === 0;
    }

    public function esPositivo(): bool
    {
        return $this->centavos > 0;
    }

    public function esNegativo(): bool
    {
        return $this->centavos < 0;
    }

    public function igualA(self $otro): bool
    {
        return $this->centavos === $otro->centavos;
    }

    public function mayorQue(self $otro): bool
    {
        return $this->centavos > $otro->centavos;
    }

    public function menorQue(self $otro): bool
    {
        return $this->centavos < $otro->centavos;
    }

    public function mayorOIgualQue(self $otro): bool
    {
        return $this->centavos >= $otro->centavos;
    }

    public function menorOIgualQue(self $otro): bool
    {
        return $this->centavos <= $otro->centavos;
    }

    public function menorEntre(self $otro): self
    {
        return $this->menorOIgualQue($otro) ? $this : $otro;
    }

    public function mayorEntre(self $otro): self
    {
        return $this->mayorOIgualQue($otro) ? $this : $otro;
    }

    /** Nunca negativo — usar en saldos que el dominio garantiza no-negativos (ej. saldo_capital, saldo de documento). */
    public function noNegativo(): self
    {
        return $this->esNegativo() ? self::cero() : $this;
    }

    public function jsonSerialize(): string
    {
        return $this->aDecimalString();
    }

    private static function centavosDesdePesos(int|float|string $pesos): int
    {
        if (is_int($pesos)) {
            return $pesos * 100;
        }

        if (is_string($pesos)) {
            $valor = trim($pesos);

            if (! preg_match('/^-?\d+(\.\d{1,2})?$/', $valor)) {
                throw new InvalidArgumentException("Valor decimal de dinero inválido: {$pesos}");
            }

            [$entero, $decimal] = array_pad(explode('.', $valor, 2), 2, '0');
            $signo = str_starts_with($entero, '-') ? -1 : 1;
            $entero = ltrim($entero, '-');
            $decimal = str_pad(substr($decimal, 0, 2), 2, '0');

            return $signo * ((int) $entero * 100 + (int) $decimal);
        }

        return (int) round($pesos * 100);
    }
}
