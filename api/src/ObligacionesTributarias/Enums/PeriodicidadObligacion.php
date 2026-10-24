<?php

namespace App\ObligacionesTributarias\Enums;

enum PeriodicidadObligacion: string
{
    case Mensual = 'mensual';
    case Bimestral = 'bimestral';
    case Cuatrimestral = 'cuatrimestral';
    case Anual = 'anual';
    case Unica = 'unica';
}
