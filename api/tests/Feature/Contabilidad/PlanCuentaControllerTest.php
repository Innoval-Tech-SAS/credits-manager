<?php

namespace Tests\Feature\Contabilidad;

use App\Contabilidad\Enums\NaturalezaCuenta;
use App\Contabilidad\Enums\TipoCuenta;
use App\Contabilidad\Models\PlanCuenta;
use App\Contabilidad\Models\SaldoApertura;
use App\Core\Dinero\Dinero;
use App\Empresas\Models\Empresa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\AfirmaAuditoria;
use Tests\Concerns\CreatesContabilidadFixtures;
use Tests\TestCase;

class PlanCuentaControllerTest extends TestCase
{
    use ActsAsAdmin;
    use AfirmaAuditoria;
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

    private function registrarMovimiento(PlanCuenta $cuenta): void
    {
        $this->postJson('/api/v1/contabilidad/comprobantes', [
            'tipo_comprobante_id' => $this->crearTipoComprobante()->id,
            'fecha' => '2026-01-15',
            'concepto' => 'Movimiento de prueba',
            'lineas' => [
                ['cuenta_id' => $cuenta->id, 'debito' => 100, 'credito' => 0],
                ['cuenta_id' => $this->crearCuenta()->id, 'debito' => 0, 'credito' => 100],
            ],
        ])->assertCreated();
    }

    public function test_edita_codigo_naturaleza_tipo_nivel_y_padre_de_una_cuenta_sin_movimientos(): void
    {
        $this->actingAsAdmin();
        $padre = $this->crearCuenta(['codigo' => '41', 'tipo' => TipoCuenta::Ingreso]);
        $cuenta = $this->crearCuenta(['codigo' => '1105']);

        $respuesta = $this->patchJson("/api/v1/contabilidad/plan-cuentas/{$cuenta->id}", [
            'codigo' => '4135',
            'naturaleza' => NaturalezaCuenta::Credito->value,
            'tipo' => TipoCuenta::Ingreso->value,
            'nivel' => 2,
            'cuenta_padre_id' => $padre->id,
        ]);

        $respuesta->assertStatus(200)->assertJsonPath('cuenta.codigo', '4135');
        $this->assertDatabaseHas('plan_cuentas', [
            'id' => $cuenta->id,
            'codigo' => '4135',
            'naturaleza' => NaturalezaCuenta::Credito->value,
            'tipo' => TipoCuenta::Ingreso->value,
            'nivel' => 2,
            'cuenta_padre_id' => $padre->id,
        ]);
    }

    public function test_quita_la_cuenta_padre(): void
    {
        $this->actingAsAdmin();
        $padre = $this->crearCuenta();
        $cuenta = $this->crearCuenta(['cuenta_padre_id' => $padre->id]);

        $this->patchJson("/api/v1/contabilidad/plan-cuentas/{$cuenta->id}", ['cuenta_padre_id' => null])->assertStatus(200);

        $this->assertDatabaseHas('plan_cuentas', ['id' => $cuenta->id, 'cuenta_padre_id' => null]);
    }

    public function test_conservar_el_mismo_codigo_no_choca_con_la_unicidad(): void
    {
        $this->actingAsAdmin();
        $cuenta = $this->crearCuenta(['codigo' => '1105']);

        $this->patchJson("/api/v1/contabilidad/plan-cuentas/{$cuenta->id}", ['codigo' => '1105', 'nombre' => 'Caja'])->assertStatus(200);

        $this->assertDatabaseHas('plan_cuentas', ['id' => $cuenta->id, 'nombre' => 'Caja']);
    }

    public function test_rechaza_editar_a_un_codigo_que_ya_usa_otra_cuenta_de_la_empresa(): void
    {
        $this->actingAsAdmin();
        $this->crearCuenta(['codigo' => '1105']);
        $cuenta = $this->crearCuenta(['codigo' => '1110']);

        $respuesta = $this->patchJson("/api/v1/contabilidad/plan-cuentas/{$cuenta->id}", ['codigo' => '1105']);

        $respuesta->assertStatus(422)->assertJsonValidationErrors('codigo');
    }

    public function test_permite_un_codigo_que_solo_existe_en_otra_empresa(): void
    {
        $this->actingAsAdmin();
        $this->crearCuenta(['codigo' => '1105']);

        $this->actingAsAdmin();
        $cuenta = $this->crearCuenta(['codigo' => '1110']);

        $this->patchJson("/api/v1/contabilidad/plan-cuentas/{$cuenta->id}", ['codigo' => '1105'])->assertStatus(200);

        $this->assertDatabaseHas('plan_cuentas', ['id' => $cuenta->id, 'codigo' => '1105']);
    }

    public function test_rechaza_valores_invalidos_de_naturaleza_tipo_y_nivel(): void
    {
        $this->actingAsAdmin();
        $cuenta = $this->crearCuenta();

        $respuesta = $this->patchJson("/api/v1/contabilidad/plan-cuentas/{$cuenta->id}", [
            'naturaleza' => 'neutra',
            'tipo' => 'otro',
            'nivel' => 0,
        ]);

        $respuesta->assertStatus(422)->assertJsonValidationErrors(['naturaleza', 'tipo', 'nivel']);
    }

    public function test_rechaza_que_la_cuenta_sea_su_propio_padre(): void
    {
        $this->actingAsAdmin();
        $cuenta = $this->crearCuenta();

        $respuesta = $this->patchJson("/api/v1/contabilidad/plan-cuentas/{$cuenta->id}", ['cuenta_padre_id' => $cuenta->id]);

        $respuesta->assertStatus(422)->assertJsonValidationErrors('cuenta_padre_id');
    }

    public function test_rechaza_un_padre_que_es_descendiente_de_la_cuenta(): void
    {
        $this->actingAsAdmin();
        $abuela = $this->crearCuenta();
        $hija = $this->crearCuenta(['cuenta_padre_id' => $abuela->id]);
        $nieta = $this->crearCuenta(['cuenta_padre_id' => $hija->id]);

        $respuesta = $this->patchJson("/api/v1/contabilidad/plan-cuentas/{$abuela->id}", ['cuenta_padre_id' => $nieta->id]);

        $respuesta->assertStatus(422)->assertJsonValidationErrors('cuenta_padre_id');
        $this->assertDatabaseHas('plan_cuentas', ['id' => $abuela->id, 'cuenta_padre_id' => null]);
    }

    public function test_rechaza_un_padre_de_otra_empresa(): void
    {
        $this->actingAsAdmin();
        $ajena = $this->crearCuenta();

        $this->actingAsAdmin();
        $cuenta = $this->crearCuenta();

        $respuesta = $this->patchJson("/api/v1/contabilidad/plan-cuentas/{$cuenta->id}", ['cuenta_padre_id' => $ajena->id]);

        $respuesta->assertStatus(422)->assertJsonValidationErrors('cuenta_padre_id');
    }

    public function test_cuenta_de_otra_empresa_devuelve_404(): void
    {
        $this->actingAsAdmin();
        $ajena = $this->crearCuenta(['codigo' => '1105']);

        $this->actingAsAdmin();

        $this->patchJson("/api/v1/contabilidad/plan-cuentas/{$ajena->id}", ['codigo' => '9999'])->assertStatus(404);

        $this->assertDatabaseHas('plan_cuentas', ['id' => $ajena->id, 'codigo' => '1105']);
    }

    public function test_ignora_empresa_id_y_campos_de_auditoria_en_el_payload(): void
    {
        $this->actingAsAdmin();
        $otraEmpresaId = $this->crearCuenta()->empresa_id;

        $usuario = $this->actingAsAdmin();
        $cuenta = $this->crearCuenta();
        $creadoEn = $cuenta->creado_en->toDateTimeString();

        $this->patchJson("/api/v1/contabilidad/plan-cuentas/{$cuenta->id}", [
            'nombre' => 'Caja menor',
            'empresa_id' => $otraEmpresaId,
            'creado_en' => '2000-01-01 00:00:00',
            'borrado_en' => '2000-01-01 00:00:00',
        ])->assertStatus(200);

        $this->assertDatabaseHas('plan_cuentas', [
            'id' => $cuenta->id,
            'nombre' => 'Caja menor',
            'empresa_id' => $usuario->empresa_id,
            'creado_en' => $creadoEn,
            'borrado_en' => null,
        ]);
    }

    public function test_bloquea_cambiar_codigo_naturaleza_o_tipo_de_una_cuenta_con_movimientos(): void
    {
        $this->actingAsAdmin();
        $cuenta = $this->crearCuenta(['codigo' => '1105']);
        $this->registrarMovimiento($cuenta);

        $respuesta = $this->patchJson("/api/v1/contabilidad/plan-cuentas/{$cuenta->id}", [
            'codigo' => '1110',
            'naturaleza' => NaturalezaCuenta::Credito->value,
            'tipo' => TipoCuenta::Pasivo->value,
        ]);

        $respuesta->assertStatus(422)->assertJsonValidationErrors(['codigo', 'naturaleza', 'tipo']);
        $this->assertDatabaseHas('plan_cuentas', ['id' => $cuenta->id, 'codigo' => '1105', 'naturaleza' => NaturalezaCuenta::Debito->value]);
    }

    public function test_bloquea_cambiar_la_naturaleza_de_una_cuenta_con_saldo_de_apertura(): void
    {
        $this->actingAsAdmin();
        $cuenta = $this->crearCuenta();
        SaldoApertura::create(['cuenta_id' => $cuenta->id, 'fecha_corte' => '2026-01-01', 'debito' => Dinero::desdeCentavos(10000), 'credito' => Dinero::cero()]);

        $respuesta = $this->patchJson("/api/v1/contabilidad/plan-cuentas/{$cuenta->id}", ['naturaleza' => NaturalezaCuenta::Credito->value]);

        $respuesta->assertStatus(422)->assertJsonValidationErrors('naturaleza');
    }

    public function test_una_cuenta_con_movimientos_si_puede_cambiar_nombre_nivel_y_padre(): void
    {
        $this->actingAsAdmin();
        $padre = $this->crearCuenta();
        $cuenta = $this->crearCuenta(['codigo' => '1105']);
        $this->registrarMovimiento($cuenta);

        $this->patchJson("/api/v1/contabilidad/plan-cuentas/{$cuenta->id}", [
            'codigo' => '1105',
            'naturaleza' => NaturalezaCuenta::Debito->value,
            'nombre' => 'Caja general',
            'nivel' => 3,
            'cuenta_padre_id' => $padre->id,
        ])->assertStatus(200);

        $this->assertDatabaseHas('plan_cuentas', ['id' => $cuenta->id, 'nombre' => 'Caja general', 'nivel' => 3, 'cuenta_padre_id' => $padre->id]);
    }

    public function test_la_bitacora_registra_los_valores_anteriores_al_editar_el_codigo(): void
    {
        $this->actingAsAdmin();
        $cuenta = $this->crearCuenta(['codigo' => '1105']);

        $this->patchJson("/api/v1/contabilidad/plan-cuentas/{$cuenta->id}", [
            'codigo' => '1110',
            'naturaleza' => NaturalezaCuenta::Credito->value,
        ])->assertStatus(200);

        $evento = $this->assertEventoAuditoria('modificar', 'contabilidad', 'plan_cuenta', $cuenta->id);
        $this->assertSame('1105', $evento['valores_anteriores']['codigo']);
        $this->assertSame(NaturalezaCuenta::Debito->value, $evento['valores_anteriores']['naturaleza']);
        $this->assertSame('1110', $evento['valores_nuevos']['codigo']);
    }

    public function test_asesor_sin_permiso_no_puede_editar(): void
    {
        $admin = $this->actingAsAdmin();
        $cuenta = $this->crearCuenta();

        $this->actingAsAsesor(Empresa::query()->findOrFail($admin->empresa_id));

        $this->patchJson("/api/v1/contabilidad/plan-cuentas/{$cuenta->id}", ['codigo' => '9999'])->assertStatus(403);
    }

    public function test_sin_autenticar_no_puede_editar(): void
    {
        $this->patchJson('/api/v1/contabilidad/plan-cuentas/1', ['codigo' => '9999'])->assertStatus(401);
    }
}
