<?php

namespace App\Importacion\Enums;

enum FaseImportacion: string
{
    case Validacion = 'validacion';
    case Escritura = 'escritura';
}
