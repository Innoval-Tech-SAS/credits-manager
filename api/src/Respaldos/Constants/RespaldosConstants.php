<?php

namespace App\Respaldos\Constants;

class RespaldosConstants
{
    public const ARCHIVO_BASE = 'base_datos.sql.gz';

    public const ARCHIVO_ARCHIVOS = 'archivos.tar.gz';

    public const ARCHIVO_SUMAS = 'SHA256SUMS';

    public const ARCHIVO_DATOS = 'respaldo.json';

    /** Nombre de cada respaldo: la fecha y hora de creación, ordenable y legible. */
    public const FORMATO_CARPETA = 'Y-m-d_His';

    /** Las carpetas temporales empiezan por punto: la retención las ignora. */
    public const PREFIJO_TEMPORAL = '.tmp-';

    public const HORA_AUTOMATICA = '01:00';
}
