<?php

namespace Tests\Feature\Creditos;

use App\Creditos\Enums\EstadoCredito;
use App\Creditos\Enums\EstadoCuota;
use App\Creditos\Enums\TipoCredito;
use App\Creditos\Enums\TipoPago;
use App\Creditos\Services\CreditoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesCreditFixtures;
use Tests\TestCase;

class CreditoServiceTest extends TestCase
{
    use CreatesCreditFixtures;
    use RefreshDatabase;

    public function test_crear_credito_nuevo_genera_las_cuotas_de_la_amortizacion(): void
    {
        $cliente = $this->crearCliente();

        $credito = app(CreditoService::class)->crearCredito([
            'cliente_id' => $cliente->id,
            'tipo_credito' => TipoCredito::Nuevo->value,
            'valor_prestamo' => 1500000,
            'tasa_interes' => 7,
            'plazo' => 6,
            'tipo_pago' => TipoPago::Mensual->value,
            'fecha_desembolso' => '2026-01-01',
        ]);

        $this->assertSame(EstadoCredito::Solicitado, $credito->estado);
        $this->assertCount(6, $credito->cuotas);
        $this->assertGreaterThan(0, (float) $credito->valor_cuota);
        $this->assertEqualsWithDelta(
            (float) $credito->valor_total,
            (float) $credito->cuotas->sum('valor'),
            0.01,
        );
    }

    public function test_refinanciacion_cierra_el_credito_origen_y_marca_pagadas_sus_cuotas_pendientes(): void
    {
        $cliente = $this->crearCliente();
        $origen = $this->crearCredito($cliente);
        $this->crearCuota($origen, ['numero_cuota' => 1, 'estado' => EstadoCuota::Pagada, 'valor' => 300, 'valor_pagado' => 300]);
        $this->crearCuota($origen, ['numero_cuota' => 2, 'estado' => EstadoCuota::Pendiente, 'valor' => 300, 'valor_pagado' => 0]);
        $this->crearCuota($origen, ['numero_cuota' => 3, 'estado' => EstadoCuota::Vencida, 'valor' => 300, 'valor_pagado' => 100]);

        $nuevo = app(CreditoService::class)->crearCredito([
            'cliente_id' => $cliente->id,
            'tipo_credito' => TipoCredito::Refinanciacion->value,
            'credito_origen_id' => $origen->id,
            'valor_prestamo' => 500,
            'tasa_interes' => 5,
            'plazo' => 3,
            'tipo_pago' => TipoPago::Mensual->value,
            'fecha_desembolso' => '2026-01-01',
        ]);

        $this->assertSame(EstadoCredito::Pagado, $origen->fresh()->estado);
        $this->assertSame(EstadoCredito::Solicitado, $nuevo->estado);
        $this->assertCount(3, $nuevo->cuotas);

        $cuotasOrigen = $origen->cuotas()->orderBy('numero_cuota')->get();

        // La cuota #1 ya estaba pagada antes de refinanciar: no se toca.
        $this->assertEquals(300, $cuotasOrigen[0]->valor_pagado);

        // Las cuotas #2 y #3 (pendiente/vencida) quedan pagadas al valor completo de la cuota.
        $this->assertSame(EstadoCuota::Pagada, $cuotasOrigen[1]->estado);
        $this->assertEquals(300, $cuotasOrigen[1]->valor_pagado);
        $this->assertSame(EstadoCuota::Pagada, $cuotasOrigen[2]->estado);
        $this->assertEquals(300, $cuotasOrigen[2]->valor_pagado);
    }

    public function test_aprueba_desembolsa_y_cancela_siguiendo_las_transiciones_validas(): void
    {
        $cliente = $this->crearCliente();
        $credito = $this->crearCredito($cliente, ['estado' => EstadoCredito::Solicitado]);

        $aprobado = app(CreditoService::class)->aprobar($credito);
        $this->assertSame(EstadoCredito::Aprobado, $aprobado->estado);

        $desembolsado = app(CreditoService::class)->desembolsar($aprobado);
        $this->assertSame(EstadoCredito::Desembolsado, $desembolsado->estado);
    }

    public function test_cancela_un_credito_solicitado_o_aprobado(): void
    {
        $cliente = $this->crearCliente();

        $solicitado = $this->crearCredito($cliente, ['estado' => EstadoCredito::Solicitado]);
        $this->assertSame(EstadoCredito::Cancelado, app(CreditoService::class)->cancelar($solicitado)->estado);

        $aprobado = $this->crearCredito($cliente, ['estado' => EstadoCredito::Aprobado]);
        $this->assertSame(EstadoCredito::Cancelado, app(CreditoService::class)->cancelar($aprobado)->estado);
    }

    public function test_rechaza_aprobar_un_credito_que_no_esta_solicitado(): void
    {
        $cliente = $this->crearCliente();
        $credito = $this->crearCredito($cliente, ['estado' => EstadoCredito::Desembolsado]);

        $this->expectException(ValidationException::class);

        app(CreditoService::class)->aprobar($credito);
    }

    public function test_rechaza_desembolsar_un_credito_que_no_esta_aprobado(): void
    {
        $cliente = $this->crearCliente();
        $credito = $this->crearCredito($cliente, ['estado' => EstadoCredito::Solicitado]);

        $this->expectException(ValidationException::class);

        app(CreditoService::class)->desembolsar($credito);
    }

    public function test_rechaza_cancelar_un_credito_desembolsado(): void
    {
        $cliente = $this->crearCliente();
        $credito = $this->crearCredito($cliente, ['estado' => EstadoCredito::Desembolsado]);

        $this->expectException(ValidationException::class);

        app(CreditoService::class)->cancelar($credito);
    }
}
