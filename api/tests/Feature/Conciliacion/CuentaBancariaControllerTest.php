<?php

namespace Tests\Feature\Conciliacion;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesConciliacionFixtures;
use Tests\TestCase;

class CuentaBancariaControllerTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesConciliacionFixtures;
    use RefreshDatabase;

    private const BASE = '/api/v1/conciliacion/cuentas-bancarias';

    public function test_sin_autenticar_no_puede_listar(): void
    {
        $this->getJson(self::BASE)->assertStatus(401);
    }

    public function test_asesor_sin_permiso_no_puede_crear(): void
    {
        $this->actingAsAsesor();

        $this->postJson(self::BASE, $this->datosValidos())->assertStatus(403);
    }

    public function test_valida_los_campos_requeridos(): void
    {
        $this->actingAsAdmin();

        $this->postJson(self::BASE, [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['banco', 'numero_cuenta', 'tipo_cuenta', 'plan_cuenta_id']);
    }

    public function test_rechaza_numero_de_cuenta_duplicado_en_la_misma_empresa(): void
    {
        $this->actingAsAdmin();
        $this->crearCuentaBancaria(['numero_cuenta' => '111']);

        $this->postJson(self::BASE, array_merge($this->datosValidos(), ['numero_cuenta' => '111']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('numero_cuenta');
    }

    public function test_crea_una_cuenta_bancaria(): void
    {
        $this->actingAsAdmin();

        $respuesta = $this->postJson(self::BASE, $this->datosValidos())->assertStatus(201);

        $respuesta->assertJsonPath('cuenta_bancaria.numero_cuenta', '38800005237');
        $this->assertDatabaseHas('cuentas_bancarias', ['numero_cuenta' => '38800005237', 'banco' => 'bancolombia']);
    }

    public function test_edita_una_cuenta_bancaria(): void
    {
        $this->actingAsAdmin();
        $cuenta = $this->crearCuentaBancaria(['alias' => 'Original']);

        $respuesta = $this->patchJson(self::BASE."/{$cuenta->id}", ['alias' => 'Cuenta nómina']);

        $respuesta->assertStatus(200)->assertJsonPath('cuenta_bancaria.alias', 'Cuenta nómina');
    }

    public function test_un_admin_no_puede_editar_una_cuenta_bancaria_de_otra_empresa(): void
    {
        $this->actingAsAdmin();
        $cuentaAjena = $this->crearCuentaBancaria();

        $this->actingAsAdmin();

        $this->patchJson(self::BASE."/{$cuentaAjena->id}", ['alias' => 'Intento ajeno'])->assertStatus(404);
    }

    public function test_un_plan_cuenta_de_otra_empresa_responde_422(): void
    {
        $admin = $this->actingAsAdmin();

        $this->actingAsAdmin();
        $cuentaAjena = $this->crearCuenta();

        $this->actingAs($admin, 'sanctum');

        $this->postJson(self::BASE, array_merge($this->datosValidos(), ['plan_cuenta_id' => $cuentaAjena->id]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('plan_cuenta_id');
    }

    public function test_un_admin_no_ve_cuentas_bancarias_de_otra_empresa_en_el_listado(): void
    {
        $this->actingAsAdmin();
        $this->crearCuentaBancaria(['alias' => 'Empresa A']);

        $this->actingAsAdmin();
        $this->crearCuentaBancaria(['alias' => 'Empresa B']);

        $respuesta = $this->getJson(self::BASE);

        $respuesta->assertStatus(200);
        $this->assertCount(1, $respuesta->json('cuentas_bancarias'));
        $this->assertSame('Empresa B', $respuesta->json('cuentas_bancarias.0.alias'));
    }

    private function datosValidos(): array
    {
        return [
            'banco' => 'bancolombia',
            'numero_cuenta' => '38800005237',
            'tipo_cuenta' => 'ahorros',
            'alias' => 'Cuenta principal',
            'plan_cuenta_id' => $this->crearCuenta()->id,
        ];
    }
}
