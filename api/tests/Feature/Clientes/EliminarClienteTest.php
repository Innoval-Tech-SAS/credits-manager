<?php

namespace Tests\Feature\Clientes;

use App\Creditos\Enums\EstadoCredito;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesCreditFixtures;
use Tests\TestCase;

class EliminarClienteTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesCreditFixtures;
    use RefreshDatabase;

    public function test_elimina_un_cliente_sin_creditos(): void
    {
        $this->actingAsAdmin();
        $cliente = $this->crearCliente();

        $respuesta = $this->deleteJson("/api/v1/clientes/{$cliente->id}");

        $respuesta->assertStatus(204);
        $this->assertSoftDeleted('clientes', ['id' => $cliente->id]);
    }

    public function test_permite_eliminar_cliente_con_creditos_pagados_o_cancelados(): void
    {
        $this->actingAsAdmin();
        $cliente = $this->crearCliente();
        $this->crearCredito($cliente, ['estado' => EstadoCredito::Pagado]);
        $this->crearCredito($cliente, ['estado' => EstadoCredito::Cancelado]);

        $respuesta = $this->deleteJson("/api/v1/clientes/{$cliente->id}");

        $respuesta->assertStatus(204);
        $this->assertSoftDeleted('clientes', ['id' => $cliente->id]);
    }

    public function test_rechaza_eliminar_cliente_con_credito_desembolsado(): void
    {
        $this->actingAsAdmin();
        $cliente = $this->crearCliente();
        $this->crearCredito($cliente, ['estado' => EstadoCredito::Desembolsado]);

        $respuesta = $this->deleteJson("/api/v1/clientes/{$cliente->id}");

        $respuesta->assertStatus(422)->assertJsonValidationErrors('cliente');
        $this->assertDatabaseHas('clientes', ['id' => $cliente->id, 'borrado_en' => null]);
    }

    public function test_cliente_inexistente_devuelve_404(): void
    {
        $this->actingAsAdmin();

        $respuesta = $this->deleteJson('/api/v1/clientes/999999');

        $respuesta->assertStatus(404);
    }

    public function test_sin_autenticar_no_puede_eliminar(): void
    {
        $this->actingAsAdmin();
        $cliente = $this->crearCliente();
        $this->salirDeSesion();

        $respuesta = $this->deleteJson("/api/v1/clientes/{$cliente->id}");

        $respuesta->assertStatus(401);
    }

    public function test_asesor_no_puede_eliminar(): void
    {
        $usuario = $this->actingAsAsesor();
        $cliente = $this->crearCliente(['empresa_id' => $usuario->empresa_id]);

        $respuesta = $this->deleteJson("/api/v1/clientes/{$cliente->id}");

        $respuesta->assertStatus(403);
    }
}
