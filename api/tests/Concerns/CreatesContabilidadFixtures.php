<?php

namespace Tests\Concerns;

use App\Contabilidad\Enums\EventoContable;
use App\Contabilidad\Enums\NaturalezaCuenta;
use App\Contabilidad\Enums\TipoCuenta;
use App\Contabilidad\Models\PlanCuenta;
use App\Contabilidad\Models\ReglaContabilizacion;
use App\Contabilidad\Models\TipoComprobante;
use App\Terceros\Enums\TipoDocumentoTercero;
use App\Terceros\Models\Tercero;

/**
 * Helpers de fixtures para tests de Contabilidad/Terceros — evita repetir la
 * creación de cuenta/tipo de comprobante/regla en cada test.
 */
trait CreatesContabilidadFixtures
{
    protected function crearCuenta(array $overrides = []): PlanCuenta
    {
        static $contador = 0;
        $contador++;

        return PlanCuenta::create(array_merge([
            'codigo' => "1{$contador}05",
            'nombre' => "Cuenta de prueba {$contador}",
            'naturaleza' => NaturalezaCuenta::Debito,
            'tipo' => TipoCuenta::Activo,
            'permite_movimiento' => true,
        ], $overrides));
    }

    protected function crearTipoComprobante(array $overrides = []): TipoComprobante
    {
        static $contador = 0;
        $contador++;

        return TipoComprobante::create(array_merge([
            'codigo' => "CI{$contador}",
            'nombre' => "Comprobante de prueba {$contador}",
        ], $overrides));
    }

    protected function crearRegla(EventoContable $evento, ?PlanCuenta $debito = null, ?PlanCuenta $credito = null, ?TipoComprobante $tipo = null): ReglaContabilizacion
    {
        return ReglaContabilizacion::create([
            'evento' => $evento,
            'cuenta_debito_id' => ($debito ?? $this->crearCuenta())->id,
            'cuenta_credito_id' => ($credito ?? $this->crearCuenta())->id,
            'tipo_comprobante_id' => ($tipo ?? $this->crearTipoComprobante())->id,
        ]);
    }

    protected function crearTercero(array $overrides = []): Tercero
    {
        static $contador = 0;
        $contador++;

        return Tercero::create(array_merge([
            'tipo_documento' => TipoDocumentoTercero::Nit,
            'numero_documento' => "90000000{$contador}",
            'nombre' => "Tercero de prueba {$contador}",
        ], $overrides));
    }
}
