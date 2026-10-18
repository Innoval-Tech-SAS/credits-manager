<?php

namespace Tests\Feature\Gastos;

use App\Causacion\Models\ParametrizacionTipoGasto;
use App\Empresas\Models\Empresa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesContabilidadFixtures;
use Tests\Concerns\CreatesGastosFixtures;
use Tests\TestCase;

class EditarParametrizacionTipoGastoTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesContabilidadFixtures;
    use CreatesGastosFixtures;
    use RefreshDatabase;

    private const BASE = '/api/v1/gastos/parametrizaciones';

    private function datosEdicion(array $overrides = []): array
    {
        return [
            'tipo_comprobante_id' => $this->crearTipoComprobante()->id,
            'cuenta_gasto_id' => $this->crearCuenta()->id,
            'cuenta_cxp_id' => $this->crearCuenta()->id,
            'cuenta_iva_id' => null,
            'tarifa_iva' => 19,
            'aplica_retefuente' => false,
            'aplica_reteica' => false,
            'aplica_reteiva' => false,
            ...$overrides,
        ];
    }

    public function test_sin_autenticar_responde_401(): void
    {
        $this->getJson(self::BASE.'/1')->assertStatus(401);
        $this->patchJson(self::BASE.'/1', [])->assertStatus(401);
    }

    public function test_sin_permiso_responde_403(): void
    {
        $admin = $this->actingAsAdmin();
        $parametrizacion = $this->crearParametrizacion($this->crearTipoGasto());
        $this->actingAsAsesor(Empresa::findOrFail($admin->empresa_id));

        $this->patchJson(self::BASE."/{$parametrizacion->id}", [])->assertStatus(403);
    }

    public function test_una_parametrizacion_inexistente_responde_404(): void
    {
        $this->actingAsAdmin();

        $this->getJson(self::BASE.'/999999')->assertStatus(404);
        $this->patchJson(self::BASE.'/999999', $this->datosEdicion())->assertStatus(404);
    }

    public function test_valida_los_campos_obligatorios_y_las_retenciones_que_aplican(): void
    {
        $this->actingAsAdmin();
        $parametrizacion = $this->crearParametrizacion($this->crearTipoGasto());

        $this->patchJson(self::BASE."/{$parametrizacion->id}", ['aplica_retefuente' => true])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'tipo_comprobante_id', 'cuenta_gasto_id', 'cuenta_cxp_id',
                'tarifa_retefuente_persona_natural', 'tarifa_retefuente_persona_juridica', 'cuenta_retefuente_id',
            ]);
    }

    public function test_muestra_una_parametrizacion(): void
    {
        $this->actingAsAdmin();
        $parametrizacion = $this->crearParametrizacion($this->crearTipoGasto());

        $this->getJson(self::BASE."/{$parametrizacion->id}")
            ->assertOk()
            ->assertJsonPath('parametrizacion.id', $parametrizacion->id);
    }

    public function test_edita_cuentas_y_retenciones_sin_cambiar_tipo_de_gasto_ni_vigencia(): void
    {
        $this->actingAsAdmin();
        $tipoGasto = $this->crearTipoGasto();
        $parametrizacion = $this->crearParametrizacion($tipoGasto, ['fecha_desde' => '2026-01-01']);
        $cuentaRetefuente = $this->crearCuenta();
        $datos = $this->datosEdicion([
            'aplica_retefuente' => true,
            'tarifa_retefuente_persona_natural' => 10,
            'tarifa_retefuente_persona_juridica' => 11,
            'cuenta_retefuente_id' => $cuentaRetefuente->id,
            'tipo_gasto_id' => $this->crearTipoGasto()->id,
            'fecha_desde' => '2030-01-01',
        ]);

        $this->patchJson(self::BASE."/{$parametrizacion->id}", $datos)
            ->assertOk()
            ->assertJsonPath('parametrizacion.aplica_retefuente', true);

        $this->assertDatabaseHas('parametrizaciones_tipo_gasto', [
            'id' => $parametrizacion->id,
            'tipo_gasto_id' => $tipoGasto->id,
            'fecha_desde' => '2026-01-01',
            'cuenta_gasto_id' => $datos['cuenta_gasto_id'],
            'cuenta_cxp_id' => $datos['cuenta_cxp_id'],
            'aplica_retefuente' => true,
            'cuenta_retefuente_id' => $cuentaRetefuente->id,
        ]);
        $this->assertSame(1, ParametrizacionTipoGasto::where('tipo_gasto_id', $tipoGasto->id)->count());
    }

    public function test_apagar_una_retencion_limpia_su_cuenta_y_tarifa(): void
    {
        $this->actingAsAdmin();
        $parametrizacion = $this->crearParametrizacion($this->crearTipoGasto(), [
            'aplica_reteiva' => true,
            'tarifa_reteiva' => 15,
            'cuenta_reteiva_id' => $this->crearCuenta()->id,
        ]);

        $this->patchJson(self::BASE."/{$parametrizacion->id}", $this->datosEdicion(['aplica_reteiva' => false]))->assertOk();

        $this->assertDatabaseHas('parametrizaciones_tipo_gasto', [
            'id' => $parametrizacion->id,
            'aplica_reteiva' => false,
            'tarifa_reteiva' => null,
            'cuenta_reteiva_id' => null,
        ]);
    }

    public function test_no_ve_ni_edita_la_parametrizacion_de_otra_empresa(): void
    {
        $this->actingAsAdmin();
        $ajena = $this->crearParametrizacion($this->crearTipoGasto());

        $this->actingAsAdmin();

        $this->getJson(self::BASE."/{$ajena->id}")->assertStatus(404);
        $this->patchJson(self::BASE."/{$ajena->id}", $this->datosEdicion())->assertStatus(404);
    }

    public function test_rechaza_una_cuenta_de_otra_empresa(): void
    {
        $this->actingAsAdmin();
        $cuentaAjena = $this->crearCuenta();

        $this->actingAsAdmin();
        $parametrizacion = $this->crearParametrizacion($this->crearTipoGasto());

        $this->patchJson(self::BASE."/{$parametrizacion->id}", $this->datosEdicion(['cuenta_cxp_id' => $cuentaAjena->id]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('cuenta_cxp_id');
    }
}
