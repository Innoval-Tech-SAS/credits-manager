<?php

namespace Tests\Feature\Clientes;

use App\Creditos\Enums\EstadoCredito;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesCreditFixtures;
use Tests\TestCase;

class ListarClientesTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesCreditFixtures;
    use RefreshDatabase;

    public function test_lista_incluye_activos_e_inactivos_por_defecto(): void
    {
        $this->actingAsAdmin();
        $this->crearCliente(['activo' => true]);
        $this->crearCliente(['activo' => false]);

        $respuesta = $this->getJson('/api/v1/clientes');

        $respuesta->assertStatus(200);
        $this->assertCount(2, $respuesta->json('data'));
    }

    public function test_filtra_por_estado_activo(): void
    {
        $this->actingAsAdmin();
        $this->crearCliente(['activo' => true]);
        $this->crearCliente(['activo' => false]);

        $respuesta = $this->getJson('/api/v1/clientes?estado=activo');

        $respuesta->assertStatus(200);
        $this->assertCount(1, $respuesta->json('data'));
        $this->assertTrue($respuesta->json('data.0.activo'));
    }

    public function test_filtra_por_estado_inactivo(): void
    {
        $this->actingAsAdmin();
        $this->crearCliente(['activo' => true]);
        $this->crearCliente(['activo' => false]);

        $respuesta = $this->getJson('/api/v1/clientes?estado=inactivo');

        $respuesta->assertStatus(200);
        $this->assertCount(1, $respuesta->json('data'));
        $this->assertFalse($respuesta->json('data.0.activo'));
    }

    public function test_filtra_por_estado_de_credito(): void
    {
        $this->actingAsAdmin();
        $clienteConDesembolsado = $this->crearCliente();
        $this->crearCredito($clienteConDesembolsado, ['estado' => EstadoCredito::Desembolsado]);

        $clienteConPagado = $this->crearCliente();
        $this->crearCredito($clienteConPagado, ['estado' => EstadoCredito::Pagado]);

        $clienteSinCredito = $this->crearCliente();

        $respuesta = $this->getJson('/api/v1/clientes?estado_credito=desembolsado');

        $respuesta->assertStatus(200);
        $this->assertCount(1, $respuesta->json('data'));
        $this->assertSame($clienteConDesembolsado->id, $respuesta->json('data.0.id'));
        $this->assertNotNull($clienteSinCredito);
    }

    public function test_sin_autenticar_no_puede_listar(): void
    {
        $respuesta = $this->getJson('/api/v1/clientes');

        $respuesta->assertStatus(401);
    }

    public function test_asesor_sin_permiso_no_puede_listar(): void
    {
        $this->actingAsAsesor();

        $respuesta = $this->getJson('/api/v1/clientes');

        $respuesta->assertStatus(403);
    }
}
