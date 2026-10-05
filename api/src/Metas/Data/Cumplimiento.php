<?php

namespace App\Metas\Data;

use App\Core\Dinero\Dinero;
use App\Metas\Enums\EstadoMeta;

/**
 * Resultado de una meta en un período: objetivo, ejecutado, cumplimiento en centésimas (10000 = 100 %) y estado.
 * El porcentaje se compara en centésimas enteras; solo se convierte a texto para mostrarlo.
 */
final readonly class Cumplimiento
{
    private function __construct(
        public Dinero $objetivo,
        public Dinero $ejecutado,
        public int $centesimas,
        public EstadoMeta $estado,
    ) {}

    /** Período cerrado: cumplida al 100 %, parcial desde el mínimo, incumplida debajo. Abierto: alcanzada, en riesgo o en curso. */
    public static function calcular(Dinero $objetivo, Dinero $ejecutado, bool $cerrado, ParametrosMetas $parametros, int $centesimasDeDias): self
    {
        $centesimas = $objetivo->esCero() ? 0 : intdiv($ejecutado->centavos() * 10000, $objetivo->centavos());

        $estado = $cerrado
            ? self::estadoCerrado($centesimas, $parametros)
            : self::estadoAbierto($centesimas, $centesimasDeDias, $parametros);

        return new self($objetivo, $ejecutado, $centesimas, $estado);
    }

    /** Un cierre guarda el porcentaje como decimal; se vuelve a centésimas para comparar igual que en vivo. */
    public static function desdeCierre(Dinero $objetivo, Dinero $ejecutado, string $porcentaje, EstadoMeta $estado): self
    {
        return new self($objetivo, $ejecutado, (int) round((float) $porcentaje * 100), $estado);
    }

    public function brecha(): Dinero
    {
        return $this->objetivo->restar($this->ejecutado)->noNegativo();
    }

    public function porcentaje(): string
    {
        return sprintf('%d.%02d', intdiv($this->centesimas, 100), $this->centesimas % 100);
    }

    /** @return array<string, mixed> */
    public function aArray(): array
    {
        return [
            'objetivo' => $this->objetivo,
            'ejecutado' => $this->ejecutado,
            'porcentaje' => $this->porcentaje(),
            'brecha' => $this->brecha(),
            'estado' => $this->estado,
        ];
    }

    private static function estadoCerrado(int $centesimas, ParametrosMetas $parametros): EstadoMeta
    {
        return match (true) {
            $centesimas >= 10000 => EstadoMeta::Cumplida,
            $centesimas >= $parametros->cumplimientoParcialMinimo * 100 => EstadoMeta::CumplidaParcial,
            default => EstadoMeta::Incumplida,
        };
    }

    private static function estadoAbierto(int $centesimas, int $centesimasDeDias, ParametrosMetas $parametros): EstadoMeta
    {
        if ($parametros->alertaAlcanzadaActiva && $centesimas >= $parametros->umbralAlcanzada * 100) {
            return EstadoMeta::Alcanzada;
        }

        if ($centesimas < $centesimasDeDias - $parametros->toleranciaRiesgoPuntos * 100) {
            return EstadoMeta::EnRiesgo;
        }

        return EstadoMeta::EnCurso;
    }
}
