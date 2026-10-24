<?php

namespace App\Recibos\Enums;

/** De cuál de los dos documentos nace el recibo: un pago de crédito o un cobro de factura. Mutuamente excluyentes (ver el `CHECK` de la migración). */
enum OrigenReciboCaja: string
{
    case Pago = 'pago';
    case CobroFacturacion = 'cobro_facturacion';
}
