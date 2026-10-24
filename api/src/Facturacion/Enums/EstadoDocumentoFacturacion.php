<?php

namespace App\Facturacion\Enums;

/**
 * «Pagada» y «anulada» se derivan de los pagos y de las notas, no se guardan, para que no puedan quedar
 * desalineados con ellos. `PendienteAprobacion` es propio de la nota crédito manual (7.1.2): se solicita con un
 * usuario y queda sin número/asiento hasta que otro distinto la aprueba — ver `DocumentoFacturacionService`.
 */
enum EstadoDocumentoFacturacion: string
{
    case Borrador = 'borrador';
    case PendienteAprobacion = 'pendiente_aprobacion';
    case Emitido = 'emitido';
}
