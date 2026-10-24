<?php

namespace Tests\Feature\Creditos;

use App\Contabilidad\Enums\EventoContable;
use App\Creditos\Enums\EstadoCredito;
use App\Creditos\Enums\TipoCredito;
use App\Creditos\Enums\TipoPago;
use App\Creditos\Services\CreditoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\ActsAsAdmin;
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
    use ActsAsAdmin;
    use CreatesContabilidadFixtures;
    use CreatesCreditFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsAdmin();
    }

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
            'total_debito' => 100000,
            'total_credito' => 100000,
        ]);
        $this->assertDatabaseHas('asientos_contables', ['cuenta_id' => $cuentaCartera->id, 'debito' => 100000, 'tercero_id' => $cliente->tercero_id]);
        $this->assertDatabaseHas('asientos_contables', ['cuenta_id' => $cuentaBancos->id, 'credito' => 100000, 'tercero_id' => $cliente->tercero_id]);
    }

    public function test_desembolso_se_rechaza_sin_regla_configurada_y_el_credito_no_queda_desembolsado(): void
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

        $this->expectException(ValidationException::class);

        try {
            app(CreditoService::class)->desembolsar($credito);
        } finally {
            $this->assertSame(EstadoCredito::Aprobado, $credito->refresh()->estado);
            $this->assertDatabaseCount('comprobantes', 0);
        }
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
