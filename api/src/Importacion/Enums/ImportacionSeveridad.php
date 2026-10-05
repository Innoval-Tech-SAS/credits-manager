<?php

namespace App\Importacion\Enums;

enum ImportacionSeveridad: string
{
    case Error = 'error';
    case Aviso = 'aviso';
}
