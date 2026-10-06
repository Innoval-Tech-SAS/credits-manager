<?php

namespace App\Contabilidad\Enums;

enum EstadoComprobante: string
{
    case Borrador = 'borrador';
    case Revisado = 'revisado';
    case Contabilizado = 'contabilizado';
    case Anulado = 'anulado';
}
