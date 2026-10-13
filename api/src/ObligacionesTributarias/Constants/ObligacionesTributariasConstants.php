<?php

namespace App\ObligacionesTributarias\Constants;

class ObligacionesTributariasConstants
{
    public const MODULO = 'obligaciones_tributarias';

    /** Misma zona que la base (`APP_TIMEZONE`): la ventana de alerta y el "vencida" calculado se miden en hora de Colombia. */
    public const ZONA_HORARIA = 'America/Bogota';

    public const DIAS_ALERTA_PREVIA_DEFECTO = 5;
}
