<?php

namespace Tests\Feature\Pagos;

use App\Creditos\Enums\EstadoCuota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesContabilidadFixtures;
use Tests\Concerns\CreatesCreditFixtures;
use Tests\TestCase;

class CrearPagoValidationTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesContabilidadFixtures;
    use CreatesCreditFixtures;
    use RefreshDatabase;

    public function test_rechaza_valor_de_pago_cero_o_negativo(): void
    {
        $this->actingAsAdmin();
        $cliente = $this->crearCliente();
        $credito = $this->crearCredito($cliente);
        $this->crearCuota($credito);

        foreach ([0, -50] as $valorInvalido) {
            $respuesta = $this->postJson("/api/v1/creditos/{$credito->id}/pagos", [
                'valor_pago' => $valorInvalido,
                'forma_pago' => 'efectivo',
            ]);

            $respuesta->assertStatus(422)->assertJsonValidationErrors('valor_pago');
        }

        $this->assertDatabaseCount('pagos', 0);
    }

    public function test_rechaza_valor_de_pago_faltante(): void
    {
        $this->actingAsAdmin();
        $cliente = $this->crearCliente();
        $credito = $this->crearCredito($cliente);
        $this->crearCuota($credito);

        $respuesta = $this->postJson("/api/v1/creditos/{$credito->id}/pagos", [
            'forma_pago' => 'efectivo',
        ]);

        $respuesta->assertStatus(422)->assertJsonValidationErrors('valor_pago');
    }

    public function test_rechaza_forma_de_pago_fuera_del_enum(): void
    {
        $this->actingAsAdmin();
        $cliente = $this->crearCliente();
        $credito = $this->crearCredito($cliente);
        $this->crearCuota($credito);

        $respuesta = $this->postJson("/api/v1/creditos/{$credito->id}/pagos", [
            'valor_pago' => 100,
            'forma_pago' => 'cheque',
        ]);

        $respuesta->assertStatus(422)->assertJsonValidationErrors('forma_pago');
        $this->assertDatabaseCount('pagos', 0);
    }

    public function test_rechaza_observacion_demasiado_larga(): void
    {
        $this->actingAsAdmin();
        $cliente = $this->crearCliente();
        $credito = $this->crearCredito($cliente);
        $this->crearCuota($credito);

        $respuesta = $this->postJson("/api/v1/creditos/{$credito->id}/pagos", [
            'valor_pago' => 100,
            'forma_pago' => 'efectivo',
            'observacion' => str_repeat('a', 256),
        ]);

        $respuesta->assertStatus(422)->assertJsonValidationErrors('observacion');
    }

    public function test_credito_inexistente_devuelve_404(): void
    {
        $this->actingAsAdmin();

        $respuesta = $this->postJson('/api/v1/creditos/999999/pagos', [
            'valor_pago' => 100,
            'forma_pago' => 'efectivo',
        ]);

        $respuesta->assertStatus(404);
    }

    public function test_pago_valido_se_registra_y_actualiza_la_cuota(): void
    {
        $this->actingAsAdmin();
        $this->crearReglasDeCreditoYPago();
        $cliente = $this->crearCliente();
        $credito = $this->crearCredito($cliente);
        $this->crearCuota($credito, ['valor' => 300]);

        $respuesta = $this->postJson("/api/v1/creditos/{$credito->id}/pagos", [
            'valor_pago' => 300,
            'forma_pago' => 'transferencia',
        ]);

        $respuesta->assertStatus(201);
        $this->assertDatabaseCount('pagos', 1);
        $this->assertSame(EstadoCuota::Pagada, $credito->cuotas()->first()->fresh()->estado);
    }

    public function test_sin_autenticar_no_puede_registrar_pagos(): void
    {
        $this->actingAsAdmin();
        $cliente = $this->crearCliente();
        $credito = $this->crearCredito($cliente);
        $this->salirDeSesion();

        $respuesta = $this->postJson("/api/v1/creditos/{$credito->id}/pagos", [
            'valor_pago' => 100,
            'forma_pago' => 'efectivo',
        ]);

        $respuesta->assertStatus(401);
    }

    public function test_asesor_sin_permiso_de_pagos_no_puede_registrar(): void
    {
        $this->actingAsAsesor();
        $cliente = $this->crearCliente();
        $credito = $this->crearCredito($cliente);

        $respuesta = $this->postJson("/api/v1/creditos/{$credito->id}/pagos", [
            'valor_pago' => 100,
            'forma_pago' => 'efectivo',
        ]);

        $respuesta->assertStatus(403);
        $this->assertDatabaseCount('pagos', 0);
    }
}
