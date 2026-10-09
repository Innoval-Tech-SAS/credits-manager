<?php

namespace App\Contabilidad\Enums;

enum NaturalezaDocumento: string
{
    case PorPagar = 'por_pagar';
    case PorCobrar = 'por_cobrar';
    /** Pagamos antes de recibir la factura: queda como saldo a favor que luego se cruza contra una PorPagar real. */
    case AnticipoProveedor = 'anticipo_proveedor';
    /** El cliente pagó antes de la factura: saldo a favor que luego se cruza contra una PorCobrar real. */
    case AnticipoCliente = 'anticipo_cliente';
}
