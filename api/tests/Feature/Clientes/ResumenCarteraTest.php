<?php

namespace Tests\Feature\Clientes;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesCreditFixtures;
use Tests\TestCase;

class ResumenCarteraTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesCreditFixtures;
    use RefreshDatabase;

    public function test_el_resumen_indica_las_cuotas_vencidas_y_los_dias_de_mora(): void
    {
        $this->actingAsAdmin();
        $cliente = $this->crearCliente();
        $credito = $this->crearCredito($cliente);
        $this->crearCuota($credito, ['numero_cuota' => 1, 'fecha_vencimiento' => now()->subDays(10)->toDateString(), 'valor' => 300, 'valor_pagado' => 0, 'estado' => 'vencida']);
        $this->crearCuota($credito, ['numero_cuota' => 2, 'fecha_vencimiento' => now()->addDays(20)->toDateString(), 'valor' => 500, 'valor_pagado' => 0, 'estado' => 'pendiente']);

        $respuesta = $this->getJson("/api/v1/clientes/{$cliente->id}/resumen");

        $respuesta->assertOk();
        $this->assertSame(1, $respuesta->json('cartera.cuotas_vencidas'));
        $this->assertSame(10, $respuesta->json('cartera.dias_mora'));
        $this->assertSame('300.00', $respuesta->json('cartera.saldo_en_mora'));
    }

    public function test_un_cliente_al_dia_no_tiene_dias_de_mora(): void
    {
        $this->actingAsAdmin();
        $cliente = $this->crearCliente();
        $credito = $this->crearCredito($cliente);
        $this->crearCuota($credito, ['numero_cuota' => 1, 'fecha_vencimiento' => now()->addDays(10)->toDateString(), 'estado' => 'pendiente']);

        $respuesta = $this->getJson("/api/v1/clientes/{$cliente->id}/resumen");

        $respuesta->assertOk();
        $this->assertSame(0, $respuesta->json('cartera.cuotas_vencidas'));
        $this->assertSame(0, $respuesta->json('cartera.dias_mora'));
    }
}
