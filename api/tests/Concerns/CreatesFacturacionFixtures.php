<?php

namespace Tests\Concerns;

use App\Contabilidad\Enums\EventoContable;
use App\Contabilidad\Models\ReglaContabilizacion;

/** Reglas de contabilización de la facturación. Idempotente: crea solo las que falten en la empresa del usuario autenticado. */
trait CreatesFacturacionFixtures
{
    use CreatesContabilidadFixtures;

    protected function crearReglasFacturacion(): void
    {
        foreach ([EventoContable::FacturaEmision, EventoContable::NotaCreditoFactura, EventoContable::NotaDebitoFactura, EventoContable::CobroFactura] as $evento) {
            if (ReglaContabilizacion::query()->where('evento', $evento)->doesntExist()) {
                $this->crearRegla($evento);
            }
        }
    }
}
