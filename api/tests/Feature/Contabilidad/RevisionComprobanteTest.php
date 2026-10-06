<?php

namespace Tests\Feature\Contabilidad;

use App\Contabilidad\Enums\TipoCuenta;
use App\Contabilidad\Models\CentroCosto;
use App\Contabilidad\Models\Comprobante;
use App\Empresas\Models\Empresa;
use App\Usuarios\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\AfirmaAuditoria;
use Tests\Concerns\CreatesAuditoriaFixtures;
use Tests\Concerns\CreatesContabilidadFixtures;
use Tests\TestCase;

/** Flujo de asientos manuales: borrador → revisado → contabilizado, con segregación entre quien crea y quien revisa. */
class RevisionComprobanteTest extends TestCase
{
    use ActsAsAdmin;
    use AfirmaAuditoria;
    use CreatesAuditoriaFixtures;
    use CreatesContabilidadFixtures;
    use RefreshDatabase;

    private const BASE = '/api/v1/contabilidad/comprobantes';

    /** Crea un borrador como usuario con permiso de crear; las cuentas y el tipo quedan en la empresa de la sesión. */
    private function crearBorrador(int $total = 100): int
    {
        $payload = [
            'tipo_comprobante_id' => $this->crearTipoComprobante()->id,
            'fecha' => '2026-01-15',
            'concepto' => 'Asiento manual de prueba',
            'lineas' => [
                ['cuenta_id' => $this->crearCuenta()->id, 'debito' => $total, 'credito' => 0],
                ['cuenta_id' => $this->crearCuenta()->id, 'debito' => 0, 'credito' => $total],
            ],
        ];

        return $this->postJson(self::BASE, $payload)->assertCreated()->json('comprobante.id');
    }

    private function creador(Empresa $empresa): void
    {
        $this->actingAsRolConPermisos('creador', ['contabilidad.crear_manual', 'contabilidad.revisar', 'contabilidad.contabilizar', 'contabilidad.listar', 'contabilidad.anular'], $empresa);
    }

    private function revisor(Empresa $empresa): Usuario
    {
        return $this->actingAsRolConPermisos('revisor', ['contabilidad.revisar', 'contabilidad.contabilizar', 'contabilidad.listar', 'informes_contables.listar'], $empresa);
    }

    public function test_sin_autenticar_no_puede_revisar(): void
    {
        $this->postJson(self::BASE.'/1/revisar')->assertStatus(401);
    }

    public function test_sin_permiso_de_revisar_no_puede_revisar(): void
    {
        $empresa = $this->crearEmpresaDePrueba();
        $this->creador($empresa);
        $id = $this->crearBorrador();

        $this->actingAsRolConPermisos('solo_consulta', ['contabilidad.listar'], $empresa);

        $this->postJson(self::BASE."/{$id}/revisar")->assertStatus(403);
        $this->assertDatabaseHas('comprobantes', ['id' => $id, 'estado' => 'borrador']);
    }

    public function test_quien_creo_el_asiento_no_puede_revisarlo(): void
    {
        $empresa = $this->crearEmpresaDePrueba();
        $this->creador($empresa);
        $id = $this->crearBorrador();

        $this->postJson(self::BASE."/{$id}/revisar")
            ->assertStatus(422)
            ->assertJsonValidationErrors('comprobante');

        $this->assertDatabaseHas('comprobantes', ['id' => $id, 'estado' => 'borrador', 'revisado_por' => null]);
    }

    public function test_otro_usuario_revisa_el_borrador_y_queda_registrado(): void
    {
        $empresa = $this->crearEmpresaDePrueba();
        $this->creador($empresa);
        $id = $this->crearBorrador();
        $revisor = $this->revisor($empresa);

        $this->postJson(self::BASE."/{$id}/revisar")
            ->assertOk()
            ->assertJsonPath('comprobante.estado', 'revisado');

        $this->assertDatabaseHas('comprobantes', ['id' => $id, 'estado' => 'revisado', 'revisado_por' => $revisor->id]);
        $this->assertDatabaseHas('comprobantes', ['id' => $id, 'contabilizado_en' => null]);
        $this->assertEventoAuditoria('revisar', 'contabilidad', 'comprobante', $id);
    }

    public function test_no_se_revisa_dos_veces(): void
    {
        $empresa = $this->crearEmpresaDePrueba();
        $this->creador($empresa);
        $id = $this->crearBorrador();
        $this->revisor($empresa);
        $this->postJson(self::BASE."/{$id}/revisar")->assertOk();

        $this->postJson(self::BASE."/{$id}/revisar")
            ->assertStatus(422)
            ->assertJsonValidationErrors('comprobante');
    }

    public function test_contabilizar_exige_que_el_asiento_este_revisado(): void
    {
        $empresa = $this->crearEmpresaDePrueba();
        $this->creador($empresa);
        $id = $this->crearBorrador();
        $this->revisor($empresa);

        $this->postJson(self::BASE."/{$id}/contabilizar")
            ->assertStatus(422)
            ->assertJsonValidationErrors('comprobante');
    }

    public function test_contabilizar_publica_el_asiento_revisado(): void
    {
        $empresa = $this->crearEmpresaDePrueba();
        $this->creador($empresa);
        $id = $this->crearBorrador();
        $revisor = $this->revisor($empresa);
        $this->postJson(self::BASE."/{$id}/revisar")->assertOk();

        $this->postJson(self::BASE."/{$id}/contabilizar")
            ->assertOk()
            ->assertJsonPath('comprobante.estado', 'contabilizado');

        $this->assertDatabaseHas('comprobantes', ['id' => $id, 'estado' => 'contabilizado', 'contabilizado_por' => $revisor->id]);
        $this->assertDatabaseCount('asientos_contables', 2);
        $this->assertEventoAuditoria('contabilizar', 'contabilidad', 'comprobante', $id);
    }

    public function test_el_balance_solo_suma_comprobantes_contabilizados(): void
    {
        $empresa = $this->crearEmpresaDePrueba();
        $this->creador($empresa);
        $id = $this->crearBorrador(250);
        $this->revisor($empresa);

        $this->getJson('/api/v1/contabilidad/balance-prueba?hasta=2026-12-31')
            ->assertOk()
            ->assertJsonPath('total_debito', '0.00');

        $this->postJson(self::BASE."/{$id}/revisar")->assertOk();
        $this->postJson(self::BASE."/{$id}/contabilizar")->assertOk();

        $this->getJson('/api/v1/contabilidad/balance-prueba?hasta=2026-12-31')
            ->assertOk()
            ->assertJsonPath('total_debito', '250.00')
            ->assertJsonPath('total_credito', '250.00');
    }

    public function test_anular_un_borrador_no_genera_reverso(): void
    {
        $empresa = $this->crearEmpresaDePrueba();
        $this->creador($empresa);
        $id = $this->crearBorrador();
        $antes = Comprobante::query()->count();

        $this->postJson(self::BASE."/{$id}/reversar", ['motivo' => 'Digitado con error'])
            ->assertStatus(201)
            ->assertJsonPath('comprobante.estado', 'anulado');

        $this->assertSame($antes, Comprobante::query()->count());
        $this->assertDatabaseHas('comprobantes', ['id' => $id, 'estado' => 'anulado', 'comprobante_reverso_id' => null]);
    }

    public function test_un_asiento_manual_con_cuenta_de_gasto_exige_centro_de_costo(): void
    {
        $empresa = $this->crearEmpresaDePrueba();
        $this->creador($empresa);
        $cuentaGasto = $this->crearCuenta(['tipo' => TipoCuenta::Gasto]);
        $cuentaBanco = $this->crearCuenta();
        $payload = [
            'tipo_comprobante_id' => $this->crearTipoComprobante()->id,
            'fecha' => '2026-01-15',
            'concepto' => 'Gasto manual',
            'lineas' => [
                ['cuenta_id' => $cuentaGasto->id, 'debito' => 80, 'credito' => 0],
                ['cuenta_id' => $cuentaBanco->id, 'debito' => 0, 'credito' => 80],
            ],
        ];

        $this->postJson(self::BASE, $payload)->assertStatus(422)->assertJsonValidationErrors('lineas');

        $centro = CentroCosto::query()->create(['codigo' => 'ADM', 'nombre' => 'Administración', 'activo' => true]);
        $payload['lineas'][0]['centro_costo_id'] = $centro->id;

        $this->postJson(self::BASE, $payload)->assertStatus(201);
    }

    public function test_sin_autenticar_no_puede_contabilizar(): void
    {
        $this->postJson(self::BASE.'/1/contabilizar')->assertStatus(401);
    }

    public function test_un_comprobante_de_otra_empresa_responde_404_al_revisar_y_contabilizar(): void
    {
        $empresa = $this->crearEmpresaDePrueba();
        $otraEmpresa = $this->crearEmpresaDePrueba();

        $this->creador($otraEmpresa);
        $ajeno = $this->crearBorrador();

        $this->revisor($empresa);
        $this->postJson(self::BASE."/{$ajeno}/revisar")->assertStatus(404);
        $this->postJson(self::BASE."/{$ajeno}/contabilizar")->assertStatus(404);
        $this->assertDatabaseHas('comprobantes', ['id' => $ajeno, 'estado' => 'borrador']);
    }
}
