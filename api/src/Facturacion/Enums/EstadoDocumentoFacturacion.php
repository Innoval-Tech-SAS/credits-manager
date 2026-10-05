<?php

namespace App\Facturacion\Enums;

/**
 * Solo dos estados guardados: el número existe desde la emisión. «Pagada» y «anulada» se derivan de los
 * pagos y de las notas, no se guardan, para que no puedan quedar desalineados con ellos.
 */
enum EstadoDocumentoFacturacion: string
{
    case Borrador = 'borrador';
    case Emitido = 'emitido';
}
