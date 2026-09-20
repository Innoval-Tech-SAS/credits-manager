<?php

namespace App\Exportacion\Constants;

class ExportacionConstants
{
    /** Límite de Excel: 1 048 576 filas por hoja, una de ellas es el encabezado. */
    public const FILAS_MAXIMAS = 1048575;

    public const DISCO = 'exportaciones';

    /** Conexión de cola explícita: no depende de QUEUE_CONNECTION. */
    public const CONEXION_COLA = 'redis';

    public const COLA = 'exportaciones';

    public const TIMEOUT_JOB_SEGUNDOS = 3600;

    public const HOJA_DATOS = 'Datos';

    public const HOJA_PARAMETROS = 'Parámetros';

    public const FORMATO_MONETARIO = '#,##0.00';

    public const FORMATO_DECIMAL = '#,##0.00##';

    public const FORMATO_PORCENTAJE = '0.00%';

    public const FORMATO_FECHA = 'yyyy-mm-dd';

    public const FORMATO_FECHA_HORA = 'yyyy-mm-dd hh:mm:ss';
}
