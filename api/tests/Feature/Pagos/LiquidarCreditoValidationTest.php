<?php

namespace Tests\Feature\Pagos;

use App\Creditos\Enums\EstadoCredito;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesCreditFixtures;
use Tests\TestCase;

class LiquidarCreditoValidationTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesCreditFixtures;
    use RefreshDatabase;

    public function test_rechaza_forma_de_pago_faltante(): void
    {
        $this->actingAsAdmin();
        $cliente = $this->crearCliente();
        $credito = $this->crearCredito($cliente);
        $this->crearCuota($credito);

        $respuesta = $this->postJson("/api/v1/creditos/{$credito->id}/liquidacion", []);

        $respuesta->assertStatus(422)->assertJsonValidationErrors('forma_pago');
    }

    public function test_rechaza_forma_de_pago_fuera_del_enum(): void
    {
        $this->actingAsAdmin();
        $cliente = $this->crearCliente();
        $credito = $this->crearCredito($cliente);
        $this->crearCuota($credito);

        $respuesta = $this->postJson("/api/v1/creditos/{$credito->id}/liquidacion", ['forma_pago' => 'cheque']);

        $respuesta->assertStatus(422)->assertJsonValidationErrors('forma_pago');
    }

    public function test_rechaza_observacion_demasiado_larga(): void
    {
        $this->actingAsAdmin();
        $cliente = $this->crearCliente();
        $credito = $this->crearCredito($cliente);
        $this->crearCuota($credito);

        $respuesta = $this->postJson("/api/v1/creditos/{$credito->id}/liquidacion", [
            'forma_pago' => 'efectivo',
            'observacion' => str_repeat('a', 256),
        ]);

        $respuesta->assertStatus(422)->assertJsonValidationErrors('observacion');
    }

    public function test_credito_inexistente_devuelve_404_al_cotizar(): void
    {
        $this->actingAsAdmin();

        $respuesta = $this->getJson('/api/v1/creditos/999999/liquidacion');

        $respuesta->assertStatus(404);
    }

    public function test_credito_inexistente_devuelve_404_al_liquidar(): void
    {
        $this->actingAsAdmin();

        $respuesta = $this->postJson('/api/v1/creditos/999999/liquidacion', ['forma_pago' => 'efectivo']);

        $respuesta->assertStatus(404);
    }

    public function test_cotiza_la_liquidacion_sin_registrarla(): void
    {
        $this->actingAsAdmin();
        $cliente = $this->crearCliente();
        $credito = $this->crearCredito($cliente);
        $this->crearCuota($credito, ['valor' => 300]);

        $respuesta = $this->getJson("/api/v1/creditos/{$credito->id}/liquidacion");

        $respuesta->assertStatus(200)->assertJsonStructure([
            'capital_pendiente', 'dias_transcurridos', 'tasa_diaria', 'interes', 'total', 'fecha_referencia',
        ]);
        $this->assertDatabaseCount('pagos', 0);
    }

    public function test_liquidacion_valida_registra_el_pago_y_marca_el_credito_pagado(): void
    {
        $this->actingAsAdmin();
        $cliente = $this->crearCliente();
        $credito = $this->crearCredito($cliente);
        $this->crearCuota($credito, ['valor' => 300]);

        $respuesta = $this->postJson("/api/v1/creditos/{$credito->id}/liquidacion", ['forma_pago' => 'transferencia']);

        $respuesta->assertStatus(201);
        $this->assertDatabaseCount('pagos', 1);
        $this->assertSame(EstadoCredito::Pagado, $credito->fresh()->estado);
    }

    public function test_sin_autenticar_no_puede_liquidar(): void
    {
        $this->actingAsAdmin();
        $cliente = $this->crearCliente();
        $credito = $this->crearCredito($cliente);
        $this->salirDeSesion();

        $respuesta = $this->postJson("/api/v1/creditos/{$credito->id}/liquidacion", ['forma_pago' => 'efectivo']);

        $respuesta->assertStatus(401);
    }

    public function test_asesor_sin_permiso_de_pagos_no_puede_liquidar(): void
    {
        $this->actingAsAsesor();
        $cliente = $this->crearCliente();
        $credito = $this->crearCredito($cliente);

        $respuesta = $this->postJson("/api/v1/creditos/{$credito->id}/liquidacion", ['forma_pago' => 'efectivo']);

        $respuesta->assertStatus(403);
        $this->assertDatabaseCount('pagos', 0);
    }
}
