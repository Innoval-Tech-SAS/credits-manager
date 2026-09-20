<?php

namespace Tests\Feature\Creditos;

use App\Contabilidad\Enums\EventoContable;
use App\Creditos\Enums\TipoCredito;
use App\Creditos\Enums\TipoPago;
use App\Creditos\Services\CreditoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesContabilidadFixtures;
use Tests\Concerns\CreatesCreditFixtures;
use Tests\TestCase;

/**
 * Verifica el enganche de `CreditoService::desembolsar()` con
 * `ComprobanteService::contabilizarDesembolsoCredito()` — se contabiliza recién
 * al desembolsar, no al solicitar el crédito (antes de aprobar/desembolsar no
 * ha salido dinero real).
 */
class CreditoContabilizacionTest extends TestCase
{
    use CreatesContabilidadFixtures;
    use CreatesCreditFixtures;
    use RefreshDatabase;

    public function test_desembolso_genera_comprobante_automatico_cuando_hay_regla_configurada(): void
    {
        $cuentaCartera = $this->crearCuenta();
        $cuentaBancos = $this->crearCuenta();
        $this->crearRegla(EventoContable::DesembolsoCredito, $cuentaCartera, $cuentaBancos);

        $cliente = $this->crearCliente();

        $credito = app(CreditoService::class)->crearCredito([
            'cliente_id' => $cliente->id,
            'tipo_credito' => TipoCredito::Nuevo->value,
            'valor_prestamo' => 1000,
            'tasa_interes' => 0,
            'plazo' => 2,
            'tipo_pago' => TipoPago::Mensual->value,
            'fecha_desembolso' => '2026-01-01',
        ]);
        $credito = app(CreditoService::class)->aprobar($credito);
        app(CreditoService::class)->desembolsar($credito);

        $this->assertDatabaseHas('comprobantes', [
            'modulo_origen' => 'creditos',
            'origen_id' => $credito->id,
            'estado' => 'contabilizado',
            'total_debito' => 1000,
            'total_credito' => 1000,
        ]);
        $this->assertDatabaseHas('asientos_contables', ['cuenta_id' => $cuentaCartera->id, 'debito' => 1000]);
        $this->assertDatabaseHas('asientos_contables', ['cuenta_id' => $cuentaBancos->id, 'credito' => 1000]);
    }

    public function test_desembolso_no_bloquea_la_creacion_del_credito_sin_regla_configurada(): void
    {
        $cliente = $this->crearCliente();

        $credito = app(CreditoService::class)->crearCredito([
            'cliente_id' => $cliente->id,
            'tipo_credito' => TipoCredito::Nuevo->value,
            'valor_prestamo' => 1000,
            'tasa_interes' => 0,
            'plazo' => 2,
            'tipo_pago' => TipoPago::Mensual->value,
            'fecha_desembolso' => '2026-01-01',
        ]);
        $credito = app(CreditoService::class)->aprobar($credito);
        app(CreditoService::class)->desembolsar($credito);

        $this->assertNotNull($credito->id);
        $this->assertDatabaseCount('comprobantes', 0);
    }

    public function test_solicitar_un_credito_todavia_no_genera_comprobante(): void
    {
        $cuentaCartera = $this->crearCuenta();
        $cuentaBancos = $this->crearCuenta();
        $this->crearRegla(EventoContable::DesembolsoCredito, $cuentaCartera, $cuentaBancos);

        $cliente = $this->crearCliente();

        app(CreditoService::class)->crearCredito([
            'cliente_id' => $cliente->id,
            'tipo_credito' => TipoCredito::Nuevo->value,
            'valor_prestamo' => 1000,
            'tasa_interes' => 0,
            'plazo' => 2,
            'tipo_pago' => TipoPago::Mensual->value,
            'fecha_desembolso' => '2026-01-01',
        ]);

        $this->assertDatabaseCount('comprobantes', 0);
    }
}
