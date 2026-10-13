<?php

namespace Tests\Feature\Contabilidad;

use App\Empresas\Models\Empresa;
use App\Gastos\Models\Gasto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesContabilidadFixtures;
use Tests\Concerns\CreatesGastosFixtures;
use Tests\TestCase;

class CentroCostoControllerTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesContabilidadFixtures;
    use CreatesGastosFixtures;
    use RefreshDatabase;

    public function test_crea_un_centro_de_costo(): void
    {
        $this->actingAsAdmin();

        $respuesta = $this->postJson('/api/v1/contabilidad/centros-costo', ['codigo' => 'ADM', 'nombre' => 'Administración']);

        $respuesta->assertStatus(201);
        $this->assertDatabaseHas('centros_costo', ['codigo' => 'ADM', 'nombre' => 'Administración', 'activo' => true]);
    }

    public function test_rechaza_codigo_duplicado_en_la_misma_empresa(): void
    {
        $this->actingAsAdmin();
        $this->crearCentroCosto(['codigo' => 'ADM']);

        $respuesta = $this->postJson('/api/v1/contabilidad/centros-costo', ['codigo' => 'ADM', 'nombre' => 'Administración duplicada']);

        $respuesta->assertStatus(422)->assertJsonValidationErrors('codigo');
    }

    public function test_lista_los_centros_de_costo(): void
    {
        $this->actingAsAdmin();
        $this->crearCentroCosto();
        $this->crearCentroCosto();

        $respuesta = $this->getJson('/api/v1/contabilidad/centros-costo');

        $respuesta->assertStatus(200)->assertJsonCount(2, 'data');
    }

    public function test_sin_autenticar_no_puede_listar(): void
    {
        $this->getJson('/api/v1/contabilidad/centros-costo')->assertStatus(401);
    }

    public function test_ve_el_detalle_de_un_centro_de_costo(): void
    {
        $this->actingAsAdmin();
        $centro = $this->crearCentroCosto(['codigo' => 'VTA', 'nombre' => 'Ventas']);

        $respuesta = $this->getJson("/api/v1/contabilidad/centros-costo/{$centro->id}");

        $respuesta->assertStatus(200)->assertJsonPath('centro.codigo', 'VTA');
    }

    public function test_un_centro_de_costo_inexistente_responde_404_al_ver(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/v1/contabilidad/centros-costo/999999')->assertStatus(404);
    }

    public function test_edita_un_centro_de_costo(): void
    {
        $this->actingAsAdmin();
        $centro = $this->crearCentroCosto();

        $respuesta = $this->patchJson("/api/v1/contabilidad/centros-costo/{$centro->id}", ['nombre' => 'Nuevo nombre', 'activo' => false]);

        $respuesta->assertStatus(200);
        $this->assertDatabaseHas('centros_costo', ['id' => $centro->id, 'nombre' => 'Nuevo nombre', 'activo' => false]);
    }

    public function test_editar_sin_cambiar_el_codigo_no_falla_por_duplicado(): void
    {
        $this->actingAsAdmin();
        $centro = $this->crearCentroCosto(['codigo' => 'ADM', 'nombre' => 'Administración']);

        $respuesta = $this->patchJson("/api/v1/contabilidad/centros-costo/{$centro->id}", [
            'codigo' => 'ADM',
            'nombre' => 'Administración actualizada',
        ]);

        $respuesta->assertStatus(200)->assertJsonPath('centro.nombre', 'Administración actualizada');
    }

    public function test_editar_rechaza_un_codigo_duplicado_de_otro_centro(): void
    {
        $this->actingAsAdmin();
        $this->crearCentroCosto(['codigo' => 'ADM']);
        $centro = $this->crearCentroCosto(['codigo' => 'VTA']);

        $respuesta = $this->patchJson("/api/v1/contabilidad/centros-costo/{$centro->id}", ['codigo' => 'ADM']);

        $respuesta->assertStatus(422)->assertJsonValidationErrors('codigo');
    }

    public function test_un_centro_de_costo_inexistente_responde_404_al_editar(): void
    {
        $this->actingAsAdmin();

        $this->patchJson('/api/v1/contabilidad/centros-costo/999999', ['nombre' => 'X'])->assertStatus(404);
    }

    public function test_sin_permiso_no_puede_editar(): void
    {
        $admin = $this->actingAsAdmin();
        $centro = $this->crearCentroCosto();
        $this->actingAsAsesor(Empresa::find($admin->empresa_id));

        $this->patchJson("/api/v1/contabilidad/centros-costo/{$centro->id}", ['nombre' => 'X'])->assertStatus(403);
    }

    public function test_elimina_un_centro_de_costo(): void
    {
        $this->actingAsAdmin();
        $centro = $this->crearCentroCosto();

        $this->deleteJson("/api/v1/contabilidad/centros-costo/{$centro->id}")->assertStatus(204);

        $this->assertSoftDeleted('centros_costo', ['id' => $centro->id]);
    }

    public function test_no_se_puede_eliminar_un_centro_con_gastos_asociados(): void
    {
        $this->actingAsAdmin();
        $centro = $this->crearCentroCosto();
        $tipoGasto = $this->crearTipoGasto();
        $parametrizacion = $this->crearParametrizacion($tipoGasto);

        Gasto::create([
            'tipo_gasto_id' => $tipoGasto->id,
            'centro_costo_id' => $centro->id,
            'fecha' => now()->toDateString(),
            'numero_documento' => 'FAC-001',
            'concepto' => 'Gasto de prueba',
            'valor_bruto' => 100000,
            'valor_neto' => 100000,
            'parametrizacion_tipo_gasto_id' => $parametrizacion->id,
        ]);

        $respuesta = $this->deleteJson("/api/v1/contabilidad/centros-costo/{$centro->id}");

        $respuesta->assertStatus(422)->assertJsonValidationErrors('centro_costo');
        $this->assertDatabaseHas('centros_costo', ['id' => $centro->id, 'borrado_en' => null]);
    }

    public function test_no_se_puede_eliminar_un_centro_con_asientos_contables_asociados(): void
    {
        $this->actingAsAdmin();
        $centro = $this->crearCentroCosto();
        $debito = $this->crearCuenta();
        $credito = $this->crearCuenta();

        $this->postJson('/api/v1/contabilidad/comprobantes', [
            'tipo_comprobante_id' => $this->crearTipoComprobante()->id,
            'fecha' => now()->toDateString(),
            'concepto' => 'Comprobante de prueba',
            'lineas' => [
                ['cuenta_id' => $debito->id, 'debito' => 100, 'credito' => 0, 'centro_costo_id' => $centro->id, 'descripcion' => 'Línea débito'],
                ['cuenta_id' => $credito->id, 'debito' => 0, 'credito' => 100, 'descripcion' => 'Línea crédito'],
            ],
        ])->assertCreated();

        $respuesta = $this->deleteJson("/api/v1/contabilidad/centros-costo/{$centro->id}");

        $respuesta->assertStatus(422)->assertJsonValidationErrors('centro_costo');
        $this->assertDatabaseHas('centros_costo', ['id' => $centro->id, 'borrado_en' => null]);
    }

    public function test_un_centro_de_costo_inexistente_responde_404_al_eliminar(): void
    {
        $this->actingAsAdmin();

        $this->deleteJson('/api/v1/contabilidad/centros-costo/999999')->assertStatus(404);
    }

    public function test_sin_permiso_no_puede_eliminar(): void
    {
        $admin = $this->actingAsAdmin();
        $centro = $this->crearCentroCosto();
        $this->actingAsAsesor(Empresa::find($admin->empresa_id));

        $this->deleteJson("/api/v1/contabilidad/centros-costo/{$centro->id}")->assertStatus(403);
    }

    public function test_un_admin_no_puede_ver_un_centro_de_costo_de_otra_empresa(): void
    {
        $this->actingAsAdmin();
        $centroAjeno = $this->crearCentroCosto();

        $this->actingAsAdmin();

        $this->getJson("/api/v1/contabilidad/centros-costo/{$centroAjeno->id}")->assertStatus(404);
    }

    public function test_un_admin_no_puede_editar_un_centro_de_costo_de_otra_empresa(): void
    {
        $this->actingAsAdmin();
        $centroAjeno = $this->crearCentroCosto();

        $this->actingAsAdmin();

        $respuesta = $this->patchJson("/api/v1/contabilidad/centros-costo/{$centroAjeno->id}", ['nombre' => 'Intento de edición ajena']);

        $respuesta->assertStatus(404);
    }

    public function test_un_admin_no_puede_eliminar_un_centro_de_costo_de_otra_empresa(): void
    {
        $this->actingAsAdmin();
        $centroAjeno = $this->crearCentroCosto();

        $this->actingAsAdmin();

        $respuesta = $this->deleteJson("/api/v1/contabilidad/centros-costo/{$centroAjeno->id}");

        $respuesta->assertStatus(404);
        $this->assertDatabaseHas('centros_costo', ['id' => $centroAjeno->id, 'borrado_en' => null]);
    }

    public function test_lista_los_movimientos_del_centro_de_costo(): void
    {
        $this->actingAsAdmin();
        $centro = $this->crearCentroCosto();
        $tipoGasto = $this->crearTipoGasto();
        $parametrizacion = $this->crearParametrizacion($tipoGasto);

        Gasto::create([
            'tipo_gasto_id' => $tipoGasto->id,
            'centro_costo_id' => $centro->id,
            'fecha' => '2026-02-10',
            'numero_documento' => 'FAC-900',
            'concepto' => 'Gasto de prueba',
            'valor_bruto' => 100000,
            'valor_neto' => 100000,
            'parametrizacion_tipo_gasto_id' => $parametrizacion->id,
        ]);

        $debito = $this->crearCuenta();
        $credito = $this->crearCuenta();
        $comprobante = $this->postJson('/api/v1/contabilidad/comprobantes', [
            'tipo_comprobante_id' => $this->crearTipoComprobante()->id,
            'fecha' => '2026-02-12',
            'concepto' => 'Comprobante de prueba',
            'lineas' => [
                ['cuenta_id' => $debito->id, 'debito' => 50, 'credito' => 0, 'centro_costo_id' => $centro->id, 'descripcion' => 'Línea débito'],
                ['cuenta_id' => $credito->id, 'debito' => 0, 'credito' => 50, 'descripcion' => 'Línea crédito'],
            ],
        ])->assertCreated()->json('comprobante.id');

        // Un comprobante manual nace en borrador: solo cuenta como movimiento (EfectoContable) tras contabilizarse.
        $this->postJson("/api/v1/contabilidad/comprobantes/{$comprobante}/revisar")->assertOk();
        $this->postJson("/api/v1/contabilidad/comprobantes/{$comprobante}/contabilizar")->assertOk();

        $respuesta = $this->getJson("/api/v1/contabilidad/centros-costo/{$centro->id}/movimientos");

        $respuesta->assertStatus(200)->assertJsonCount(2, 'data');
        $tipos = collect($respuesta->json('data'))->pluck('tipo')->sort()->values();
        $this->assertSame(['asiento', 'gasto'], $tipos->all());

        $movimientoGasto = collect($respuesta->json('data'))->firstWhere('tipo', 'gasto');
        $this->assertSame('FAC-900', $movimientoGasto['documento']);
        $this->assertSame('100000.00', $movimientoGasto['debito']);
    }

    public function test_un_centro_sin_movimientos_responde_la_lista_vacia(): void
    {
        $this->actingAsAdmin();
        $centro = $this->crearCentroCosto();

        $respuesta = $this->getJson("/api/v1/contabilidad/centros-costo/{$centro->id}/movimientos");

        $respuesta->assertStatus(200)->assertJsonCount(0, 'data');
    }

    public function test_sin_autenticar_no_puede_ver_movimientos(): void
    {
        $this->getJson('/api/v1/contabilidad/centros-costo/1/movimientos')->assertStatus(401);
    }

    public function test_un_centro_de_costo_inexistente_responde_404_al_ver_movimientos(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/v1/contabilidad/centros-costo/999999/movimientos')->assertStatus(404);
    }

    public function test_un_admin_no_puede_ver_movimientos_de_un_centro_de_costo_de_otra_empresa(): void
    {
        $this->actingAsAdmin();
        $centroAjeno = $this->crearCentroCosto();

        $this->actingAsAdmin();

        $this->getJson("/api/v1/contabilidad/centros-costo/{$centroAjeno->id}/movimientos")->assertStatus(404);
    }
}
