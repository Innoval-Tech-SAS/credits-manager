<?php

namespace App\Contabilidad\Enums;

enum EstadoComprobante: string
{
    case Borrador = 'borrador';
    case Contabilizado = 'contabilizado';
    case Anulado = 'anulado';
}
