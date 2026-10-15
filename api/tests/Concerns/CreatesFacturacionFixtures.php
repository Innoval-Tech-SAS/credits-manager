<?php

namespace Tests\Concerns;

use App\Contabilidad\Enums\EventoContable;
use App\Contabilidad\Models\ReglaContabilizacion;
use App\Facturacion\Enums\TipoConceptoFacturacion;
use App\Facturacion\Models\ParametrizacionConceptoFacturacion;
use Illuminate\Support\Facades\Auth;

/** Reglas de contabilización de la facturación. Idempotente: crea solo las que falten en la empresa del usuario autenticado. */
trait CreatesFacturacionFixtures
{
    use CreatesContabilidadFixtures;

    protected function crearReglasFacturacion(): void
    {
        foreach ([EventoContable::FacturaEmision, EventoContable::NotaCreditoFactura, EventoContable::NotaDebitoFactura, EventoContable::CobroFactura, EventoContable::CruceInteresFacturado] as $evento) {
            if (ReglaContabilizacion::query()->where('evento', $evento)->doesntExist()) {
                $this->crearRegla($evento);
            }
        }
    }

    /** Parametrización fiscal de un concepto para la empresa de la sesión; sin overrides, con todos los impuestos apagados (igual que la fila sembrada por defecto). */
    protected function crearParametrizacionConcepto(TipoConceptoFacturacion $tipo, array $overrides = []): ParametrizacionConceptoFacturacion
    {
        return ParametrizacionConceptoFacturacion::create(array_merge([
            'empresa_id' => Auth::user()->empresa_id,
            'tipo_concepto' => $tipo,
        ], $overrides));
    }
}
