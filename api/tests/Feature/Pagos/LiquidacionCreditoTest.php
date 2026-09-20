<?php

namespace Tests\Feature\Pagos;

use App\Creditos\Enums\EstadoCredito;
use App\Creditos\Enums\EstadoCuota;
use App\Creditos\Enums\TipoPago;
use App\Pagos\Enums\FormaPago;
use App\Pagos\Models\Pago;
use App\Pagos\Services\PagoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesCreditFixtures;
use Tests\TestCase;

class LiquidacionCreditoTest extends TestCase
{
    use CreatesCreditFixtures;
    use RefreshDatabase;

    /**
     * Reproduce el ejemplo exacto que dio el cliente: capital pendiente 1.000.000,
     * tasa mensual 2%, 15 días transcurridos desde el último pago → interés 10.000.
     */
    public function test_calcula_liquidacion_con_el_ejemplo_del_cliente(): void
    {
        $cliente = $this->crearCliente();
        $credito = $this->crearCredito($cliente, [
            'valor_prestamo' => 1_000_000,
            'tasa_interes' => 2,
            'tipo_pago' => TipoPago::Mensual,
            'fecha_desembolso' => now()->subDays(45)->toDateString(),
        ]);
        $this->crearCuota($credito, ['numero_cuota' => 1, 'valor' => 20000, 'valor_interes' => 20000, 'valor_capital' => 0]);

        Pago::create([
            'credito_id' => $credito->id,
            'cliente_id' => $cliente->id,
            'valor_pago' => 20000,
            'fecha_pago' => now()->subDays(15),
            'forma_pago' => FormaPago::Efectivo,
        ]);

        $cotizacion = app(PagoService::class)->calcularLiquidacion($credito->fresh());

        $this->assertEquals(1_000_000, $cotizacion['capital_pendiente']);
        $this->assertEquals(15, $cotizacion['dias_transcurridos']);
        $this->assertEquals(10000.0, $cotizacion['interes']);
        $this->assertEquals(1_010_000.0, $cotizacion['total']);
    }

    public function test_sin_pagos_previos_cuenta_los_dias_desde_el_desembolso(): void
    {
        $cliente = $this->crearCliente();
        $credito = $this->crearCredito($cliente, [
            'valor_prestamo' => 900,
            'tasa_interes' => 3,
            'fecha_desembolso' => now()->subDays(10)->toDateString(),
        ]);
        $this->crearCuota($credito, ['numero_cuota' => 1]);

        $cotizacion = app(PagoService::class)->calcularLiquidacion($credito->fresh());

        $this->assertEquals(10, $cotizacion['dias_transcurridos']);
        $this->assertEquals(now()->subDays(10)->toDateString(), $cotizacion['fecha_referencia']);
    }

    public function test_liquidar_credito_cierra_todas_las_cuotas_y_marca_el_credito_como_pagado(): void
    {
        $cliente = $this->crearCliente();
        $credito = $this->crearCredito($cliente, ['valor_prestamo' => 900, 'tasa_interes' => 0]);
        $this->crearCuota($credito, ['numero_cuota' => 1, 'fecha_vencimiento' => '2026-01-01']);
        $this->crearCuota($credito, ['numero_cuota' => 2, 'fecha_vencimiento' => '2026-02-01']);
        $this->crearCuota($credito, ['numero_cuota' => 3, 'fecha_vencimiento' => '2026-03-01']);

        $resultado = app(PagoService::class)->liquidarCredito($credito->fresh(), FormaPago::Transferencia, null, null);

        $cuotas = $credito->cuotas()->orderBy('numero_cuota')->get();
        foreach ($cuotas as $cuota) {
            $this->assertSame(EstadoCuota::Pagada, $cuota->estado);
            $this->assertEquals(0, $cuota->saldo_capital);
        }

        $this->assertSame(EstadoCredito::Pagado, $credito->fresh()->estado);
        $this->assertDatabaseCount('pagos', 1);
        $this->assertEquals($resultado['total'], (float) $resultado['pago']->valor_pago);
        $this->assertEquals('Liquidación anticipada del crédito', $resultado['pago']->observacion);
    }

    public function test_rechaza_liquidar_un_credito_que_no_esta_desembolsado(): void
    {
        $cliente = $this->crearCliente();
        $credito = $this->crearCredito($cliente, ['estado' => EstadoCredito::Aprobado]);
        $this->crearCuota($credito, ['numero_cuota' => 1]);

        $this->expectException(ValidationException::class);

        app(PagoService::class)->calcularLiquidacion($credito->fresh());
    }
}
