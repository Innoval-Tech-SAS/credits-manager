<?php

namespace Tests\Feature\Creditos;

use App\Core\Dinero\Dinero;
use App\Creditos\Services\CuotaService;
use App\Pagos\Enums\FormaPago;
use App\Pagos\Services\PagoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesContabilidadFixtures;
use Tests\Concerns\CreatesCreditFixtures;
use Tests\TestCase;

class CuotaServiceTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesContabilidadFixtures;
    use CreatesCreditFixtures;
    use RefreshDatabase;

    public function test_resumen_al_dia_sin_pagos_refleja_el_credito_completo_pendiente(): void
    {
        $this->actingAsAdmin();
        $cliente = $this->crearCliente();
        $credito = $this->crearCredito($cliente, ['valor_prestamo' => 900]);

        $this->crearCuota($credito, ['numero_cuota' => 1, 'valor_interes' => 10, 'valor_capital' => 300]);
        $this->crearCuota($credito, ['numero_cuota' => 2, 'valor_interes' => 7, 'valor_capital' => 300]);
        $this->crearCuota($credito, ['numero_cuota' => 3, 'valor_interes' => 3, 'valor_capital' => 300]);

        $resumen = app(CuotaService::class)->resumenAlDia($credito->id, Dinero::desdePesos(900));

        $this->assertEquals(20, $resumen['interes_pendiente']->aFloat());
        $this->assertEquals(900, $resumen['saldo_capital_pendiente']->aFloat());
        $this->assertEquals(0, $resumen['saldo_capital_pagado']->aFloat());
        $this->assertEquals([1 => 600.0, 2 => 300.0, 3 => 0.0], array_map(fn (Dinero $d) => $d->aFloat(), $resumen['saldo_capital_teorico_por_cuota']));
    }

    /**
     * Un abono que cubre el interés de la cuota 1 y una parte de su capital: el interés
     * pendiente baja solo lo que se pagó, el capital pagado sube por la diferencia, y la
     * curva teórica (basada en `valor_capital`, no en el pago real) no se mueve.
     */
    public function test_resumen_al_dia_separa_interes_y_capital_tras_un_abono_parcial(): void
    {
        $this->actingAsAdmin();
        $this->crearReglasDeCreditoYPago();
        $cliente = $this->crearCliente();
        $credito = $this->crearCredito($cliente, ['valor_prestamo' => 900]);

        $this->crearCuota($credito, ['numero_cuota' => 1, 'valor' => 310, 'valor_interes' => 10, 'valor_capital' => 300]);
        $this->crearCuota($credito, ['numero_cuota' => 2, 'valor' => 307, 'valor_interes' => 7, 'valor_capital' => 300]);
        $this->crearCuota($credito, ['numero_cuota' => 3, 'valor' => 303, 'valor_interes' => 3, 'valor_capital' => 300]);

        app(PagoService::class)->registrarPago($credito->fresh(), Dinero::desdePesos(60), FormaPago::Efectivo, null, null);

        $resumen = app(CuotaService::class)->resumenAlDia($credito->id, Dinero::desdePesos(900));

        $this->assertEquals(10, $resumen['interes_pendiente']->aFloat());
        $this->assertEquals(850, $resumen['saldo_capital_pendiente']->aFloat());
        $this->assertEquals(50, $resumen['saldo_capital_pagado']->aFloat());
        $this->assertEquals([1 => 600.0, 2 => 300.0, 3 => 0.0], array_map(fn (Dinero $d) => $d->aFloat(), $resumen['saldo_capital_teorico_por_cuota']));
    }

    public function test_resumen_al_dia_cuando_un_abono_no_alcanza_a_cubrir_el_interes(): void
    {
        $this->actingAsAdmin();
        $this->crearReglasDeCreditoYPago();
        $cliente = $this->crearCliente();
        $credito = $this->crearCredito($cliente, ['valor_prestamo' => 900]);

        $this->crearCuota($credito, ['numero_cuota' => 1, 'valor' => 310, 'valor_interes' => 10, 'valor_capital' => 300]);
        $this->crearCuota($credito, ['numero_cuota' => 2, 'valor' => 307, 'valor_interes' => 7, 'valor_capital' => 300]);
        $this->crearCuota($credito, ['numero_cuota' => 3, 'valor' => 303, 'valor_interes' => 3, 'valor_capital' => 300]);

        app(PagoService::class)->registrarPago($credito->fresh(), Dinero::desdePesos(5), FormaPago::Efectivo, null, null);

        $resumen = app(CuotaService::class)->resumenAlDia($credito->id, Dinero::desdePesos(900));

        $this->assertEquals(15, $resumen['interes_pendiente']->aFloat());
        $this->assertEquals(900, $resumen['saldo_capital_pendiente']->aFloat());
        $this->assertEquals(0, $resumen['saldo_capital_pagado']->aFloat());
    }
}
