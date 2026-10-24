<?php

namespace App\Recibos\Enums;

/** Un recibo nace emitido y pasa a anulado solo por el reverso de su pago; el número no se reutiliza. */
enum EstadoRecibo: string
{
    case Emitido = 'emitido';
    case Anulado = 'anulado';
}
