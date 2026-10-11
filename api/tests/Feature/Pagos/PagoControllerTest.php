<?php

namespace Tests\Feature\Pagos;

use App\Core\Dinero\Dinero;
use App\Creditos\Models\Credito;
use App\Pagos\Enums\FormaPago;
use App\Pagos\Services\PagoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesContabilidadFixtures;
use Tests\Concerns\CreatesCreditFixtures;
use Tests\TestCase;

class PagoControllerTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesContabilidadFixtures;
    use CreatesCreditFixtures;
    use RefreshDatabase;

    private function pagar(Credito $credito, float $valor): void
    {
        $this->crearReglasDeCreditoYPago();

        app(PagoService::class)->registrarPago($credito->fresh(), Dinero::desdePesos($valor), FormaPago::Efectivo, null, null);
    }

    public function test_lista_los_pagos_de_la_empresa_ordenados_por_fecha_descendente(): void
    {
        $this->actingAsAdmin();
        $cliente = $this->crearCliente(['nombre' => 'Juan Pérez']);
        $credito = $this->crearCredito($cliente, ['valor_prestamo' => 300]);
        $this->crearCuota($credito, ['numero_cuota' => 1, 'valor' => 100]);
        $this->crearCuota($credito, ['numero_cuota' => 2, 'valor' => 100]);
        $this->crearCuota($credito, ['numero_cuota' => 3, 'valor' => 100]);

        $this->pagar($credito, 100);
        $this->pagar($credito, 100);

        $respuesta = $this->getJson('/api/v1/pagos');

        $respuesta->assertStatus(200);
        $respuesta->assertJsonCount(2, 'data');
        $respuesta->assertJsonPath('total', 2);
    }

    public function test_busca_pagos_por_nombre_del_cliente(): void
    {
        $this->actingAsAdmin();
        $juan = $this->crearCliente(['nombre' => 'Juan Pérez']);
        $creditoJuan = $this->crearCredito($juan);
        $this->crearCuota($creditoJuan, ['numero_cuota' => 1, 'valor' => 100]);
        $this->pagar($creditoJuan, 100);

        $maria = $this->crearCliente(['nombre' => 'María Gómez']);
        $creditoMaria = $this->crearCredito($maria);
        $this->crearCuota($creditoMaria, ['numero_cuota' => 1, 'valor' => 100]);
        $this->pagar($creditoMaria, 100);

        $respuesta = $this->getJson('/api/v1/pagos?search=Gómez');

        $respuesta->assertStatus(200);
        $respuesta->assertJsonCount(1, 'data');
        $respuesta->assertJsonPath('data.0.cliente.tercero.nombre', 'María Gómez');
    }

    public function test_no_incluye_pagos_de_otra_empresa(): void
    {
        $usuarioEmpresaA = $this->actingAsAdmin();
        $clienteA = $this->crearCliente();
        $creditoA = $this->crearCredito($clienteA);
        $this->crearCuota($creditoA, ['numero_cuota' => 1, 'valor' => 100]);
        $this->pagar($creditoA, 100);

        $this->actingAsAdmin();
        $clienteB = $this->crearCliente();
        $creditoB = $this->crearCredito($clienteB);
        $this->crearCuota($creditoB, ['numero_cuota' => 1, 'valor' => 200]);
        $this->pagar($creditoB, 200);

        $respuesta = $this->actingAs($usuarioEmpresaA)->getJson('/api/v1/pagos');

        $respuesta->assertStatus(200);
        $respuesta->assertJsonCount(1, 'data');
        $respuesta->assertJsonPath('data.0.valor_pago', '100.00');
    }

    public function test_sin_autenticar_devuelve_401(): void
    {
        $this->getJson('/api/v1/pagos')->assertStatus(401);
    }
}
