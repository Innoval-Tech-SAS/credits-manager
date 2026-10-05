<?php

namespace App\Metas\Constants;

use App\Metas\Enums\BaseRecaudo;
use App\Metas\Enums\DiaDeCierre;
use App\Metas\Enums\ModoColocacionRefinanciaciones;

class MetasConstants
{
    public const MODULO = 'metas';

    /** Misma zona que la base (`APP_TIMEZONE`): los períodos y los cierres se miden en hora de Colombia. */
    public const ZONA_HORARIA = 'America/Bogota';

    public const PATRON_PERIODO = '/^\d{4}-(0[1-9]|1[0-2])$/';

    public const ROL_ASESOR = 'asesor';

    public const LIMITE_ALERTAS = 50;

    /**
     * Valores por defecto de los parámetros de cada empresa. Los marcados como «confirmar» en el roadmap
     * son propuestas pendientes de DDA; cambiarlos no toca períodos cerrados.
     */
    public const PARAMETROS_DEFECTO = [
        'tolerancia_riesgo_puntos' => 10,
        'cumplimiento_parcial_minimo' => 90,
        'alerta_alcanzada_activa' => true,
        'umbral_alcanzada' => 100,
        'modo_colocacion_refinanciaciones' => ModoColocacionRefinanciaciones::SoloDineroNuevo,
        'base_recaudo' => BaseRecaudo::Total,
        'distribucion_obligatoria_completa' => false,
        'meta_consolidada_visible_asesor' => false,
        'dia_de_cierre' => DiaDeCierre::UltimoDia,
    ];
}
