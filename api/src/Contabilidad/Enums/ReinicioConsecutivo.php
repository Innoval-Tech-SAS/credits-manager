<?php

namespace App\Contabilidad\Enums;

enum ReinicioConsecutivo: string
{
    case Nunca = 'nunca';
    case Anual = 'anual';
    case Mensual = 'mensual';
}
