<?php

namespace App\Terceros\Constants;

class TerceroConstants
{
    public const COLUMNAS_ORDENABLES = ['nombre', 'numero_documento', 'creado_en'];

    /** Valores válidos del filtro `tipo` de `GET /terceros`, según qué extensión tiene el tercero. */
    public const TIPOS_FILTRO = ['cliente', 'proveedor', 'otro'];
}
