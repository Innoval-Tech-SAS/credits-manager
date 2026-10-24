<?php

namespace App\Contabilidad\Enums;

/**
 * Naturaleza de negocio de un documento (Ingreso/Gasto/Recaudo/Pago/Ajuste) — distinta de `NaturalezaDocumento`
 * (PorPagar/PorCobrar/AnticipoProveedor/AnticipoCliente), que es la naturaleza contable del `DocumentoTercero`.
 */
enum NaturalezaNegocio: string
{
    case Ingreso = 'ingreso';
    case Gasto = 'gasto';
    case Recaudo = 'recaudo';
    case Pago = 'pago';
    case Ajuste = 'ajuste';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Ingreso => 'Ingreso',
            self::Gasto => 'Gasto',
            self::Recaudo => 'Recaudo',
            self::Pago => 'Pago',
            self::Ajuste => 'Ajuste',
        };
    }
}
