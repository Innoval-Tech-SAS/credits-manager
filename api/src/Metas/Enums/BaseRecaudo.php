<?php

namespace App\Metas\Enums;

enum BaseRecaudo: string
{
    case Total = 'total';
    case SoloCapital = 'solo_capital';
    case SoloInteres = 'solo_interes';
}
