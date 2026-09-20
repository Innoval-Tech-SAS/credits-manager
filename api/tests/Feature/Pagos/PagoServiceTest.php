<?php

namespace Tests\Feature\Pagos;

use App\Core\Dinero\Dinero;
use App\Creditos\Enums\EstadoCredito;
use App\Creditos\Enums\EstadoCuota;
use App\Creditos\Models\Credito;
use App\Pagos\Enums\FormaPago;
use App\Pagos\Services\PagoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesCreditFixtures;
use Tests\TestCase;

class PagoServiceTest extends TestCase
{
    use CreatesCreditFixtures;
    use RefreshDatabase;

    /**
     * Crédito con 3 cuotas de 300 (900 en total), sin pagos aún.
     */
    private function creditoConTresCuotas(): Credito
    {
        $cliente = $this->crearCliente();
        $credito = $this->crearCredito($cliente);

        $this->crearCuota($credito, ['numero_cuota' => 1, 'fecha_vencimiento' => '2026-01-01']);
        $this->crearCuota($credito, ['numero_cuota' => 2, 'fecha_vencimiento' => '2026-02-01']);
        $this->crearCuota($credito, ['numero_cuota' => 3, 'fecha_vencimiento' => '2026-03-01']);

        return $credito->fresh();
    }

    public function test_pago_parcial_se_aplica_a_la_cuota_mas_antigua(): void
    {
        $credito = $this->creditoConTresCuotas();

        app(PagoService::class)->registrarPago($credito, Dinero::desdePesos(100), FormaPago::Efectivo, null, null);

        $cuotas = $credito->cuotas()->orderBy('numero_cuota')->get();

        $this->assertEquals(100, $cuotas[0]->valor_pagado->aFloat());
        $this->assertSame(EstadoCuota::Pendiente, $cuotas[0]->estado);
        $this->assertEquals(0, $cuotas[1]->valor_pagado->aFloat());
        $this->assertEquals(0, $cuotas[2]->valor_pagado->aFloat());
        $this->assertSame(EstadoCredito::Desembolsado, $credito->fresh()->estado);
    }

    public function test_pago_que_completa_una_cuota_pasa_el_resto_a_la_siguiente(): void
    {
        $credito = $this->creditoConTresCuotas();

        app(PagoService::class)->registrarPago($credito, Dinero::desdePesos(350), FormaPago::Efectivo, null, null);

        $cuotas = $credito->cuotas()->orderBy('numero_cuota')->get();

        $this->assertEquals(300, $cuotas[0]->valor_pagado->aFloat());
        $this->assertSame(EstadoCuota::Pagada, $cuotas[0]->estado);
        $this->assertEquals(50, $cuotas[1]->valor_pagado->aFloat());
        $this->assertSame(EstadoCuota::Pendiente, $cuotas[1]->estado);
        $this->assertEquals(0, $cuotas[2]->valor_pagado->aFloat());
    }

    public function test_sobrepago_se_descarta_silenciosamente_y_no_se_abona_a_nada(): void
    {
        $credito = $this->creditoConTresCuotas();

        $resultado = app(PagoService::class)->registrarPago($credito, Dinero::desdePesos(1000), FormaPago::Efectivo, null, null);

        $cuotas = $credito->cuotas()->orderBy('numero_cuota')->get();

        $this->assertEquals(300, $cuotas[0]->valor_pagado->aFloat());
        $this->assertEquals(300, $cuotas[1]->valor_pagado->aFloat());
        $this->assertEquals(300, $cuotas[2]->valor_pagado->aFloat());
        $this->assertSame(EstadoCuota::Pagada, $cuotas[0]->estado);
        $this->assertSame(EstadoCuota::Pagada, $cuotas[1]->estado);
        $this->assertSame(EstadoCuota::Pagada, $cuotas[2]->estado);

        $this->assertEquals(100, $resultado['sobrante']->aFloat());
        $this->assertEquals(1000, $resultado['pago']->valor_pago->aFloat());
    }

    public function test_credito_se_marca_pagado_solo_cuando_todas_las_cuotas_quedan_pagadas(): void
    {
        $credito = $this->creditoConTresCuotas();

        app(PagoService::class)->registrarPago($credito, Dinero::desdePesos(300), FormaPago::Efectivo, null, null);
        $this->assertSame(EstadoCredito::Desembolsado, $credito->fresh()->estado);

        app(PagoService::class)->registrarPago($credito, Dinero::desdePesos(600), FormaPago::Efectivo, null, null);
        $this->assertSame(EstadoCredito::Pagado, $credito->fresh()->estado);
    }

    public function test_registra_un_pago_y_un_detalle_por_cada_cuota_tocada(): void
    {
        $credito = $this->creditoConTresCuotas();

        $resultado = app(PagoService::class)->registrarPago($credito, Dinero::desdePesos(350), FormaPago::Transferencia, 'abono parcial', null);

        $this->assertDatabaseCount('pagos', 1);
        $this->assertDatabaseHas('pagos', [
            'id' => $resultado['pago']->id,
            'credito_id' => $credito->id,
            'cliente_id' => $credito->cliente_id,
            'valor_pago' => 35000,
            'forma_pago' => FormaPago::Transferencia->value,
            'observacion' => 'abono parcial',
        ]);

        $this->assertDatabaseCount('pago_detalle', 2);
    }

    /**
     * `saldo_capital` se genera una sola vez en `AmortizacionCalculator` con valores
     * teóricos (asumiendo que cada cuota se paga completa a tiempo). Un abono parcial
     * que no alcanza a cubrir ni el interés no debe mover ese saldo hacia abajo: el
     * capital real pendiente sigue siendo el préstamo completo.
     */
    public function test_recalcula_saldo_capital_real_cuando_el_abono_no_cubre_ni_el_interes(): void
    {
        $cliente = $this->crearCliente();
        $credito = $this->crearCredito($cliente, ['valor_prestamo' => 900, 'tasa_interes' => 3.33]);

        $this->crearCuota($credito, [
            'numero_cuota' => 1, 'fecha_vencimiento' => '2026-01-01',
            'valor' => 310, 'valor_interes' => 10, 'valor_capital' => 300, 'saldo_capital' => 600,
        ]);
        $this->crearCuota($credito, [
            'numero_cuota' => 2, 'fecha_vencimiento' => '2026-02-01',
            'valor' => 307, 'valor_interes' => 7, 'valor_capital' => 300, 'saldo_capital' => 300,
        ]);
        $this->crearCuota($credito, [
            'numero_cuota' => 3, 'fecha_vencimiento' => '2026-03-01',
            'valor' => 303, 'valor_interes' => 3, 'valor_capital' => 300, 'saldo_capital' => 0,
        ]);

        app(PagoService::class)->registrarPago($credito->fresh(), Dinero::desdePesos(5), FormaPago::Efectivo, null, null);

        $cuotas = $credito->cuotas()->orderBy('numero_cuota')->get();
        $this->assertEquals(900, $cuotas[0]->saldo_capital->aFloat());

        app(PagoService::class)->registrarPago($credito->fresh(), Dinero::desdePesos(10), FormaPago::Efectivo, null, null);

        $cuotas = $credito->cuotas()->orderBy('numero_cuota')->get();
        $this->assertEquals(895, $cuotas[0]->saldo_capital->aFloat());
    }

    /**
     * Las cuotas ya vencidas también reciben el pago, en el mismo orden que
     * las pendientes (`pendientesOrdenadasParaActualizar` incluye ambos estados).
     */
    public function test_aplica_pago_a_cuotas_vencidas_igual_que_a_pendientes(): void
    {
        $cliente = $this->crearCliente();
        $credito = $this->crearCredito($cliente);
        $this->crearCuota($credito, ['numero_cuota' => 1, 'estado' => EstadoCuota::Vencida, 'fecha_vencimiento' => '2020-01-01']);
        $this->crearCuota($credito, ['numero_cuota' => 2, 'estado' => EstadoCuota::Pendiente, 'fecha_vencimiento' => '2026-06-01']);

        app(PagoService::class)->registrarPago($credito->fresh(), Dinero::desdePesos(300), FormaPago::Efectivo, null, null);

        $cuotas = $credito->cuotas()->orderBy('numero_cuota')->get();
        $this->assertSame(EstadoCuota::Pagada, $cuotas[0]->estado);
        $this->assertEquals(0, $cuotas[1]->valor_pagado->aFloat());
    }

    public function test_rechaza_registrar_pago_en_un_credito_que_no_esta_desembolsado(): void
    {
        $cliente = $this->crearCliente();
        $credito = $this->crearCredito($cliente, ['estado' => EstadoCredito::Solicitado]);
        $this->crearCuota($credito, ['numero_cuota' => 1]);

        $this->expectException(ValidationException::class);

        app(PagoService::class)->registrarPago($credito->fresh(), Dinero::desdePesos(100), FormaPago::Efectivo, null, null);
    }
}
