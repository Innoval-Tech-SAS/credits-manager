<?php

namespace App\Contabilidad\Enums;

/**
 * Eventos de negocio que las reglas de contabilización pueden mapear a cuentas.
 * Se agregan casos aquí a medida que existan módulos que los disparen (gasto,
 * factura_venta, etc. quedan para cuando existan los módulos de Gastos/Facturación).
 */
enum EventoContable: string
{
    case DesembolsoCredito = 'desembolso_credito';
    case RecaudoCapital = 'recaudo_capital';
    case RecaudoInteres = 'recaudo_interes';
}
