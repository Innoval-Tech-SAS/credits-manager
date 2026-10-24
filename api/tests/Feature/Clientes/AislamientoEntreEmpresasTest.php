<?php

namespace Tests\Feature\Clientes;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesCreditFixtures;
use Tests\TestCase;

/**
 * Prueba que EmpresaScope (App\Core\Concerns\PerteneceAEmpresa) realmente
 * aísla los datos: sin esto, el resto del feature multiempresa son columnas
 * `empresa_id` sin ningún efecto.
 */
class AislamientoEntreEmpresasTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesCreditFixtures;
    use RefreshDatabase;

    public function test_un_admin_no_ve_clientes_de_otra_empresa_en_el_listado(): void
    {
        $this->actingAsAdmin();
        $this->crearCliente(['nombre' => 'Cliente de la empresa A']);

        $this->actingAsAdmin();
        $this->crearCliente(['nombre' => 'Cliente de la empresa B']);

        $respuesta = $this->getJson('/api/v1/clientes');

        $respuesta->assertStatus(200);
        $this->assertCount(1, $respuesta->json('data'));
        $this->assertSame('Cliente de la empresa B', $respuesta->json('data.0.tercero.nombre'));
    }

    public function test_un_admin_no_puede_acceder_a_un_cliente_de_otra_empresa_por_id(): void
    {
        $this->actingAsAdmin();
        $clienteAjeno = $this->crearCliente();

        $this->actingAsAdmin();

        $respuesta = $this->getJson("/api/v1/clientes/{$clienteAjeno->id}");

        $respuesta->assertStatus(404);
    }

    public function test_un_admin_no_puede_editar_un_cliente_de_otra_empresa(): void
    {
        $this->actingAsAdmin();
        $clienteAjeno = $this->crearCliente();

        $this->actingAsAdmin();

        $respuesta = $this->patchJson("/api/v1/clientes/{$clienteAjeno->id}", [
            'nombre' => 'Intento de edición ajena',
        ]);

        $respuesta->assertStatus(404);
    }
}
