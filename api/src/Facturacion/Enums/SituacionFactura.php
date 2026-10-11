<?php

namespace App\Facturacion\Enums;

/**
 * Estado visible de una factura. Ninguno se guarda: sale de sus notas crédito y de sus cobros (ver
 * DocumentoFacturacionService::totales). Las notas no tienen situación.
 */
enum SituacionFactura: string
{
    case Emitida = 'emitida';
    case ParcialmentePagada = 'parcialmente_pagada';
    case Pagada = 'pagada';
    case ConSaldoAFavor = 'con_saldo_a_favor';
    case Anulada = 'anulada';
}
