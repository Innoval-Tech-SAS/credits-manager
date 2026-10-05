<?php

namespace App\Metas\Data;

use App\Metas\Constants\MetasConstants;
use App\Metas\Enums\DiaDeCierre;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/** Mes calendario en `America/Bogota`, identificado como `YYYY-MM`. */
final readonly class PeriodoMeta
{
    private function __construct(private string $codigo) {}

    public static function desde(string $codigo): self
    {
        if (preg_match(MetasConstants::PATRON_PERIODO, $codigo) !== 1) {
            throw new InvalidArgumentException("Período de meta inválido: {$codigo}");
        }

        return new self($codigo);
    }

    public static function actual(CarbonImmutable $hoy): self
    {
        return new self($hoy->setTimezone(MetasConstants::ZONA_HORARIA)->format('Y-m'));
    }

    public function codigo(): string
    {
        return $this->codigo;
    }

    public function inicio(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d', "{$this->codigo}-01", MetasConstants::ZONA_HORARIA)->startOfDay();
    }

    /** Primer instante fuera del período: los rangos se consultan como `[inicio, finExclusivo)`. */
    public function finExclusivo(): CarbonImmutable
    {
        return $this->inicio()->addMonth();
    }

    public function diasDelMes(): int
    {
        return $this->inicio()->daysInMonth;
    }

    public function fechaDeCierre(DiaDeCierre $diaDeCierre): CarbonImmutable
    {
        return $diaDeCierre === DiaDeCierre::UltimoDia
            ? $this->finExclusivo()->subDay()
            : $this->finExclusivo();
    }

    /** Avance esperado a la fecha, en centésimas (10000 = 100 %). Un mes futuro no tiene avance; uno pasado, todo. */
    public function centesimasDeDiasTranscurridos(CarbonImmutable $hoy): int
    {
        $hoy = $hoy->setTimezone(MetasConstants::ZONA_HORARIA)->startOfDay();

        if ($hoy < $this->inicio()) {
            return 0;
        }

        if ($hoy >= $this->finExclusivo()) {
            return 10000;
        }

        return intdiv($hoy->day * 10000, $this->diasDelMes());
    }
}
