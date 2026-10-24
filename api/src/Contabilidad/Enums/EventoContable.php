<?php

namespace App\Contabilidad\Enums;

/**
 * Eventos de negocio que las reglas de contabilización mapean a cuentas. Cada evento define la cuenta de débito y
 * la de crédito en el sentido del asiento: para la factura, débito = cuentas por cobrar y crédito = ingreso; para
 * la nota crédito, débito = ingreso (devolución) y crédito = cuentas por cobrar; para el cobro, débito = caja/banco
 * y crédito = cuentas por cobrar.
 */
enum EventoContable: string
{
    case DesembolsoCredito = 'desembolso_credito';
    case RecaudoCapital = 'recaudo_capital';
    case RecaudoInteres = 'recaudo_interes';
    case RecaudoOtrosGastos = 'recaudo_otros_gastos';
    case DevengoInteres = 'devengo_interes';
    case FacturaEmision = 'factura_emision';
    case NotaCreditoFactura = 'nota_credito_factura';
    case NotaDebitoFactura = 'nota_debito_factura';
    case CobroFactura = 'cobro_factura';
    case CruceInteresFacturado = 'cruce_interes_facturado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::DesembolsoCredito => 'Desembolso de crédito',
            self::RecaudoCapital => 'Recaudo de capital',
            self::RecaudoInteres => 'Recaudo de interés',
            self::RecaudoOtrosGastos => 'Recaudo de otros gastos',
            self::DevengoInteres => 'Devengo de interés corrido',
            self::FacturaEmision => 'Emisión de factura',
            self::NotaCreditoFactura => 'Nota crédito sobre factura',
            self::NotaDebitoFactura => 'Nota débito sobre factura',
            self::CobroFactura => 'Cobro de factura',
            self::CruceInteresFacturado => 'Cruce de interés ya recaudado contra factura',
        };
    }
}
