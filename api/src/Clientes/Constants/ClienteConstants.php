<?php

namespace App\Clientes\Constants;

use App\Terceros\Enums\TipoPersona;

class ClienteConstants
{
    /** Hoy los clientes de crédito son siempre naturales; si eso cambia, se decide aquí y en los Requests de Clientes, sin tocar Terceros. */
    public const TIPO_PERSONA = TipoPersona::Natural;

    public const COLUMNAS_ORDENABLES = ['nombre', 'numero_documento', 'empleador', 'creado_en'];
}
