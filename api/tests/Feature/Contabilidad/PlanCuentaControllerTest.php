<?php

namespace Tests\Feature\Contabilidad;

use App\Contabilidad\Enums\NaturalezaCuenta;
use App\Contabilidad\Enums\TipoCuenta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesContabilidadFixtures;
use Tests\TestCase;

class PlanCuentaControllerTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesContabilidadFixtures;
    use RefreshDatabase;

    private function payloadValido(): array
    {
        return [
            'codigo' => '1105',
            'nombre' => 'Caja general',
            'naturaleza' => NaturalezaCuenta::Debito->value,
            'tipo' => TipoCuenta::Activo->value,
            'permite_movimiento' => true,
        ];
    }

    public function test_crea_una_cuenta(): void
    {
        $this->actingAsAdmin();

        $respuesta = $this->postJson('/api/v1/contabilidad/plan-cuentas', $this->payloadValido());

        $respuesta->assertStatus(201);
        $this->assertDatabaseHas('plan_cuentas', ['codigo' => '1105', 'nombre' => 'Caja general']);
    }

    public function test_crea_una_cuenta_con_flags_de_parametrizacion(): void
    {
        $this->actingAsAdmin();

        $payload = array_merge($this->payloadValido(), [
            'codigo' => '2205',
            'exige_tercero' => true,
            'exige_documento_cruce' => true,
            'exige_vencimiento' => true,
            'exige_centro_costo' => true,
        ]);

        $respuesta = $this->postJson('/api/v1/contabilidad/plan-cuentas', $payload);

        $respuesta->assertStatus(201);
        $this->assertDatabaseHas('plan_cuentas', [
            'codigo' => '2205',
            'exige_tercero' => true,
            'exige_documento_cruce' => true,
            'exige_vencimiento' => true,
            'exige_centro_costo' => true,
        ]);
    }

    public function test_los_flags_de_parametrizacion_quedan_en_false_por_defecto(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/contabilidad/plan-cuentas', $this->payloadValido())->assertStatus(201);

        $this->assertDatabaseHas('plan_cuentas', [
            'codigo' => '1105',
            'exige_tercero' => false,
            'exige_documento_cruce' => false,
            'exige_vencimiento' => false,
            'exige_centro_costo' => false,
        ]);
    }

    public function test_rechaza_codigo_duplicado(): void
    {
        $this->actingAsAdmin();
        $this->crearCuenta(['codigo' => '1105']);

        $respuesta = $this->postJson('/api/v1/contabilidad/plan-cuentas', $this->payloadValido());

        $respuesta->assertStatus(422)->assertJsonValidationErrors('codigo');
    }

    public function test_lista_las_cuentas(): void
    {
        $this->actingAsAdmin();
        $this->crearCuenta();
        $this->crearCuenta();

        $respuesta = $this->getJson('/api/v1/contabilidad/plan-cuentas');

        $respuesta->assertStatus(200)->assertJsonCount(2, 'cuentas');
    }

    public function test_sin_autenticar_no_puede_listar(): void
    {
        $respuesta = $this->getJson('/api/v1/contabilidad/plan-cuentas');

        $respuesta->assertStatus(401);
    }

    public function test_asesor_sin_permiso_no_puede_crear(): void
    {
        $this->actingAsAsesor();

        $respuesta = $this->postJson('/api/v1/contabilidad/plan-cuentas', $this->payloadValido());

        $respuesta->assertStatus(403);
    }

    public function test_edita_una_cuenta(): void
    {
        $this->actingAsAdmin();
        $cuenta = $this->crearCuenta();

        $respuesta = $this->patchJson("/api/v1/contabilidad/plan-cuentas/{$cuenta->id}", ['activa' => false]);

        $respuesta->assertStatus(200);
        $this->assertDatabaseHas('plan_cuentas', ['id' => $cuenta->id, 'activa' => false]);
    }

    public function test_edita_los_flags_de_parametrizacion_de_una_cuenta(): void
    {
        $this->actingAsAdmin();
        $cuenta = $this->crearCuenta();

        $respuesta = $this->patchJson("/api/v1/contabilidad/plan-cuentas/{$cuenta->id}", [
            'exige_tercero' => true,
            'exige_documento_cruce' => true,
            'exige_vencimiento' => true,
            'exige_centro_costo' => true,
        ]);

        $respuesta->assertStatus(200);
        $this->assertDatabaseHas('plan_cuentas', [
            'id' => $cuenta->id,
            'exige_tercero' => true,
            'exige_documento_cruce' => true,
            'exige_vencimiento' => true,
            'exige_centro_costo' => true,
        ]);
    }

    public function test_cuenta_inexistente_devuelve_404(): void
    {
        $this->actingAsAdmin();

        $respuesta = $this->patchJson('/api/v1/contabilidad/plan-cuentas/999999', ['activa' => false]);

        $respuesta->assertStatus(404);
    }
}
