<?php

namespace Tests\Feature\Pagos;

use App\Contabilidad\Enums\EventoContable;
use App\Core\Dinero\Dinero;
use App\Pagos\Enums\FormaPago;
use App\Pagos\Services\PagoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesContabilidadFixtures;
use Tests\Concerns\CreatesCreditFixtures;
use Tests\TestCase;

/**
 * Verifica el enganche de `PagoService::registrarPago()` con
 * `ComprobanteService::contabilizarRecaudoPago()`, incluido el desglose
 * capital/interés derivado de `cuotas.valor_capital`/`valor_interes`
 * (la regla "interés primero" — ver ComprobanteService::contabilizarRecaudoPago).
 */
class PagoContabilizacionTest extends TestCase
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

    private function configurarReglas(): void
    {
        $cuentaBancos = $this->crearCuenta();
        $cuentaCartera = $this->crearCuenta();
        $cuentaIngresosIntereses = $this->crearCuenta();
        $tipo = $this->crearTipoComprobante();

        $this->crearRegla(EventoContable::RecaudoCapital, $cuentaBancos, $cuentaCartera, $tipo);
        $this->crearRegla(EventoContable::RecaudoInteres, $cuentaBancos, $cuentaIngresosIntereses, $tipo);
    }

    public function test_recaudo_separa_capital_e_interes_en_el_comprobante(): void
    {
        $this->configurarReglas();

        $cliente = $this->crearCliente();
        $credito = $this->crearCredito($cliente);
        $cuota = $this->crearCuota($credito, [
            'numero_cuota' => 1,
            'valor' => 300,
            'valor_interes' => 50,
            'valor_capital' => 250,
            'valor_pagado' => 0,
        ]);

        app(PagoService::class)->registrarPago($credito->fresh(), Dinero::desdePesos(300), FormaPago::Efectivo, null, null);

        $this->assertDatabaseHas('comprobantes', [
            'modulo_origen' => 'pagos',
            'estado' => 'contabilizado',
            'total_debito' => 30000,
            'total_credito' => 30000,
        ]);
        $this->assertDatabaseHas('asientos_contables', ['credito' => 5000, 'tercero_id' => $cliente->tercero_id]); // interés
        $this->assertDatabaseHas('asientos_contables', ['credito' => 25000, 'tercero_id' => $cliente->tercero_id]); // capital

        // La cuota sigue reflejando el mismo comportamiento que antes de este enganche.
        $this->assertEquals(300, $cuota->fresh()->valor_pagado->aFloat());
    }

    public function test_pago_solo_de_interes_no_genera_linea_de_capital(): void
    {
        $this->configurarReglas();

        $cliente = $this->crearCliente();
        $credito = $this->crearCredito($cliente);
        $this->crearCuota($credito, [
            'numero_cuota' => 1,
            'valor' => 300,
            'valor_interes' => 50,
            'valor_capital' => 250,
            'valor_pagado' => 0,
        ]);

        app(PagoService::class)->registrarPago($credito->fresh(), Dinero::desdePesos(50), FormaPago::Efectivo, null, null);

        $this->assertDatabaseCount('asientos_contables', 2); // débito banco + crédito interés, sin línea de capital
        $this->assertDatabaseHas('asientos_contables', ['debito' => 5000]);
        $this->assertDatabaseHas('asientos_contables', ['credito' => 5000]);
    }

    public function test_recaudo_se_rechaza_sin_reglas_configuradas_y_no_deja_el_pago_a_medias(): void
    {
        $cliente = $this->crearCliente();
        $credito = $this->crearCredito($cliente);
        $this->crearCuota($credito, ['numero_cuota' => 1, 'valor' => 300, 'valor_interes' => 50, 'valor_capital' => 250]);

        $this->expectException(ValidationException::class);

        try {
            app(PagoService::class)->registrarPago($credito->fresh(), Dinero::desdePesos(300), FormaPago::Efectivo, null, null);
        } finally {
            $this->assertDatabaseCount('pagos', 0);
            $this->assertDatabaseCount('comprobantes', 0);
        }
    }
}
