<?php

namespace App\Recibos\Enums;

/** Hoy solo se emiten: la anulación por reverso llega con el reverso de pagos. */
enum EstadoRecibo: string
{
    case Emitido = 'emitido';
}
