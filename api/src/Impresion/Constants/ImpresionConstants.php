<?php

namespace App\Impresion\Constants;

class ImpresionConstants
{
    /** Todos los documentos van en carta; la orientación la decide cada documento (vertical salvo excepción). */
    public const PAPEL = 'letter';

    /** Fuente base del núcleo de PDF: trae tildes y eñe sin instalar fuentes. */
    public const FUENTE = 'Helvetica';

    public const ZONA_HORARIA = 'America/Bogota';

    public const FORMATO_FECHA = 'd/m/Y';

    public const FORMATO_FECHA_HORA = 'd/m/Y, H:i';

    /** Carpeta bajo storage/app donde dompdf guarda su caché de fuentes y temporales. */
    public const CARPETA_CACHE = 'dompdf';
}
