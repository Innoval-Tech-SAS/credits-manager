<?php

namespace App\Metas\Data;

use App\Metas\Constants\MetasConstants;
use App\Metas\Enums\BaseRecaudo;
use App\Metas\Enums\DiaDeCierre;
use App\Metas\Enums\ModoColocacionRefinanciaciones;
use BackedEnum;

/** Parámetros tipados de una empresa. Lo guardado es texto (`metas_configuracion.valor`); aquí se lee una sola vez. */
final readonly class ParametrosMetas
{
    public function __construct(
        public int $toleranciaRiesgoPuntos,
        public int $cumplimientoParcialMinimo,
        public bool $alertaAlcanzadaActiva,
        public int $umbralAlcanzada,
        public ModoColocacionRefinanciaciones $modoColocacionRefinanciaciones,
        public BaseRecaudo $baseRecaudo,
        public bool $distribucionObligatoriaCompleta,
        public bool $metaConsolidadaVisibleAsesor,
        public DiaDeCierre $diaDeCierre,
    ) {}

    /** @param array<string, string> $guardados valores crudos de `metas_configuracion`; lo que falte toma el defecto */
    public static function desde(array $guardados): self
    {
        $v = [];

        foreach (MetasConstants::PARAMETROS_DEFECTO as $clave => $defecto) {
            $v[$clave] = isset($guardados[$clave]) ? self::leer($defecto, $guardados[$clave]) : $defecto;
        }

        return new self(
            toleranciaRiesgoPuntos: $v['tolerancia_riesgo_puntos'],
            cumplimientoParcialMinimo: $v['cumplimiento_parcial_minimo'],
            alertaAlcanzadaActiva: $v['alerta_alcanzada_activa'],
            umbralAlcanzada: $v['umbral_alcanzada'],
            modoColocacionRefinanciaciones: $v['modo_colocacion_refinanciaciones'],
            baseRecaudo: $v['base_recaudo'],
            distribucionObligatoriaCompleta: $v['distribucion_obligatoria_completa'],
            metaConsolidadaVisibleAsesor: $v['meta_consolidada_visible_asesor'],
            diaDeCierre: $v['dia_de_cierre'],
        );
    }

    /** @return array<string, int|bool|BackedEnum> */
    public function aArray(): array
    {
        return [
            'tolerancia_riesgo_puntos' => $this->toleranciaRiesgoPuntos,
            'cumplimiento_parcial_minimo' => $this->cumplimientoParcialMinimo,
            'alerta_alcanzada_activa' => $this->alertaAlcanzadaActiva,
            'umbral_alcanzada' => $this->umbralAlcanzada,
            'modo_colocacion_refinanciaciones' => $this->modoColocacionRefinanciaciones,
            'base_recaudo' => $this->baseRecaudo,
            'distribucion_obligatoria_completa' => $this->distribucionObligatoriaCompleta,
            'meta_consolidada_visible_asesor' => $this->metaConsolidadaVisibleAsesor,
            'dia_de_cierre' => $this->diaDeCierre,
        ];
    }

    /** @return array<string, string> forma en que se guarda cada parámetro */
    public function aFilas(): array
    {
        return array_map(
            fn (mixed $valor): string => match (true) {
                is_bool($valor) => $valor ? '1' : '0',
                $valor instanceof BackedEnum => (string) $valor->value,
                default => (string) $valor,
            },
            $this->aArray(),
        );
    }

    private static function leer(mixed $defecto, string $crudo): mixed
    {
        return match (true) {
            $defecto instanceof BackedEnum => $defecto::from($crudo),
            is_bool($defecto) => $crudo === '1',
            default => (int) $crudo,
        };
    }
}
