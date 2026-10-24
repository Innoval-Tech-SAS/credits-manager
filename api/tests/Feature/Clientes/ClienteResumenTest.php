<?php

namespace Tests\Feature\Clientes;

use App\Pagos\Enums\FormaPago;
use App\Pagos\Models\Pago;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesCreditFixtures;
use Tests\TestCase;

class ClienteResumenTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesCreditFixtures;
    use RefreshDatabase;

    public function test_resumen_agrega_creditos_saldo_y_pagos(): void
    {
        $this->actingAsAdmin();
        $cliente = $this->crearCliente();

        $creditoA = $this->crearCredito($cliente);
        $this->crearCuota($creditoA, ['valor' => 300, 'valor_pagado' => 100]);

        $creditoB = $this->crearCredito($cliente);
        $this->crearCuota($creditoB, ['valor' => 500, 'valor_pagado' => 0]);

        Pago::create([
            'credito_id' => $creditoA->id,
            'cliente_id' => $cliente->id,
            'valor_pago' => 100,
            'fecha_pago' => '2026-01-15',
            'forma_pago' => FormaPago::Efectivo,
        ]);

        $respuesta = $this->getJson("/api/v1/clientes/{$cliente->id}/resumen");

        $respuesta->assertStatus(200);
        $respuesta->assertJsonCount(2, 'creditos');
        $this->assertEqualsWithDelta(700.0, (float) $respuesta->json('saldo_consolidado'), 0.001);
        $respuesta->assertJsonCount(1, 'ultimos_pagos');
        $respuesta->assertJsonCount(0, 'documentos');
    }

    public function test_resumen_de_cliente_sin_creditos(): void
    {
        $this->actingAsAdmin();
        $cliente = $this->crearCliente();

        $respuesta = $this->getJson("/api/v1/clientes/{$cliente->id}/resumen");

        $respuesta->assertStatus(200);
        $respuesta->assertJsonCount(0, 'creditos');
        $this->assertSame(0.0, (float) $respuesta->json('saldo_consolidado'));
    }

    public function test_cliente_inexistente_devuelve_404(): void
    {
        $this->actingAsAdmin();

        $respuesta = $this->getJson('/api/v1/clientes/999999/resumen');

        $respuesta->assertStatus(404);
    }

    public function test_sin_autenticar_no_puede_ver_resumen(): void
    {
        $this->actingAsAdmin();
        $cliente = $this->crearCliente();
        $this->salirDeSesion();

        $respuesta = $this->getJson("/api/v1/clientes/{$cliente->id}/resumen");

        $respuesta->assertStatus(401);
    }

    public function test_asesor_sin_permiso_no_puede_ver_resumen(): void
    {
        $this->actingAsAsesor();
        $cliente = $this->crearCliente();

        $respuesta = $this->getJson("/api/v1/clientes/{$cliente->id}/resumen");

        $respuesta->assertStatus(403);
    }
}
