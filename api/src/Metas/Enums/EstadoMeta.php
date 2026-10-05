<?php

namespace App\Metas\Enums;

enum EstadoMeta: string
{
    case EnCurso = 'en_curso';
    case Alcanzada = 'alcanzada';
    case EnRiesgo = 'en_riesgo';
    case Cumplida = 'cumplida';
    case CumplidaParcial = 'cumplida_parcial';
    case Incumplida = 'incumplida';
}
