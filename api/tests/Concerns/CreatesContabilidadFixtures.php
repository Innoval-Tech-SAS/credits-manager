<?php

namespace Tests\Concerns;

use App\Contabilidad\Enums\EventoContable;
use App\Contabilidad\Enums\NaturalezaCuenta;
use App\Contabilidad\Enums\TipoCuenta;
use App\Contabilidad\Models\PlanCuenta;
use App\Contabilidad\Models\ReglaContabilizacion;
use App\Contabilidad\Models\TipoComprobante;
use App\Terceros\Enums\TipoPersona;
use App\Terceros\Models\Tercero;

/**
 * Helpers de fixtures para tests de Contabilidad/Terceros — evita repetir la
 * creación de cuenta/tipo de comprobante/regla en cada test.
 */
trait CreatesContabilidadFixtures
{
    use ArmaDatosDeTercero;

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

    /**
     * Las cuatro reglas que `ComprobanteService` exige para desembolsar un crédito o registrar un
     * recaudo (incluido el de otros gastos): sin ellas, esas operaciones ya no se confirman en silencio,
     * se rechazan. Úsala en el `setUp()` (o en un helper que paga/desembolsa varias veces) de los tests
     * de Créditos/Pagos que no versan sobre contabilidad — es segura de llamar más de una vez: el
     * evento es único por empresa y una ya creada no se vuelve a crear.
     */
    protected function crearReglasDeCreditoYPago(): void
    {
        foreach ([
            EventoContable::DesembolsoCredito,
            EventoContable::RecaudoCapital,
            EventoContable::RecaudoInteres,
            EventoContable::RecaudoOtrosGastos,
        ] as $evento) {
            if (ReglaContabilizacion::where('evento', $evento)->doesntExist()) {
                $this->crearRegla($evento);
            }
        }
    }

    protected function crearTercero(array $overrides = []): Tercero
    {
        static $contador = 0;
        $contador++;

        $tipoPersona = $overrides['tipo_persona'] ?? TipoPersona::Juridica;
        $nombre = $overrides['nombre'] ?? "Tercero de prueba {$contador}";
        unset($overrides['nombre']);

        $numero = $overrides['numero_documento'] ?? ($tipoPersona === TipoPersona::Juridica ? '90000000' : '100000').$contador;

        return Tercero::create(array_merge($this->datosDePersona($tipoPersona, $nombre, $numero), $overrides));
    }
}
