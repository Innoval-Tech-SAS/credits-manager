<?php

namespace Tests\Feature\Creditos;

use App\Core\Dinero\Dinero;
use App\Pagos\Enums\FormaPago;
use App\Pagos\Services\PagoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesContabilidadFixtures;
use Tests\Concerns\CreatesCreditFixtures;
use Tests\TestCase;

class EstadoCuentaFechaCorteTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesContabilidadFixtures;
    use CreatesCreditFixtures;
    use RefreshDatabase;

    public function test_el_estado_de_cuenta_trae_la_fecha_de_corte_del_servidor_y_muestra_los_pagos_reversados(): void
    {
        $this->actingAsAdmin();
        $this->crearReglasDeCreditoYPago();
        $credito = $this->crearCredito($this->crearCliente(), ['valor_prestamo' => 300, 'valor_cuota' => 100, 'valor_total' => 300, 'plazo' => 3]);
        $this->crearCuota($credito, ['numero_cuota' => 1, 'fecha_vencimiento' => now()->addMonth()->toDateString(), 'valor' => 300, 'valor_capital' => 300]);
        $pago = app(PagoService::class)->registrarPago($credito->fresh(), Dinero::desdePesos(100), FormaPago::Efectivo, null, null)['pago'];
        $this->postJson("/api/v1/pagos/{$pago->id}/reversar", ['motivo' => 'Pago registrado por error'])->assertOk();

        $respuesta = $this->getJson("/api/v1/creditos/{$credito->id}/estado-cuenta");

        $respuesta->assertOk();
        $respuesta->assertJsonPath('fecha_corte', now('America/Bogota')->toDateString());
        $respuesta->assertJsonPath('pagos.0.reversa.motivo', 'Pago registrado por error');
    }
}
