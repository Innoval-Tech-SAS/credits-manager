<?php

namespace App\Contabilidad\Enums;

/**
 * Identidad de los 6 tipos de documento de negocio que participan de cuentas por cobrar/pagar. Los valores por
 * defecto (`naturalezaPorDefecto`/`creaSaldoPorDefecto`/`cruzaSaldoPorDefecto`) reflejan el comportamiento
 * contable real, ya implementado en cada flujo (Facturación, Egresos, Gastos/Causación, Recibos): siembran
 * `tipos_documento_negocio` al crear una empresa. Lo que cada flujo consulta en tiempo de ejecución es la fila
 * de la tabla, no este enum — una empresa puede editarla.
 */
enum CodigoDocumentoNegocio: string
{
    case FacturaVenta = 'FV';
    case ComprobanteCausacion = 'CC';
    case ReciboCaja = 'RC';
    case ComprobanteEgreso = 'CE';
    case NotaCredito = 'NC';
    case NotaDebito = 'ND';

    public function nombrePorDefecto(): string
    {
        return match ($this) {
            self::FacturaVenta => 'Factura de venta',
            self::ComprobanteCausacion => 'Comprobante de causación',
            self::ReciboCaja => 'Recibo de caja',
            self::ComprobanteEgreso => 'Comprobante de egreso',
            self::NotaCredito => 'Nota crédito',
            self::NotaDebito => 'Nota débito',
        };
    }

    public function naturalezaPorDefecto(): NaturalezaNegocio
    {
        return match ($this) {
            self::FacturaVenta => NaturalezaNegocio::Ingreso,
            self::ComprobanteCausacion => NaturalezaNegocio::Gasto,
            self::ReciboCaja => NaturalezaNegocio::Recaudo,
            self::ComprobanteEgreso => NaturalezaNegocio::Pago,
            self::NotaCredito, self::NotaDebito => NaturalezaNegocio::Ajuste,
        };
    }

    public function creaSaldoPorDefecto(): bool
    {
        return match ($this) {
            self::FacturaVenta, self::ComprobanteCausacion => true,
            default => false,
        };
    }

    public function cruzaSaldoPorDefecto(): bool
    {
        return ! $this->creaSaldoPorDefecto();
    }
}
