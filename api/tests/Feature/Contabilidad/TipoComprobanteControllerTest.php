<?php

namespace Tests\Feature\Contabilidad;

use App\Causacion\Models\ParametrizacionTipoGasto;
use App\Causacion\Models\TipoGasto;
use App\Contabilidad\Enums\EventoContable;
use App\Empresas\Models\Empresa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesContabilidadFixtures;
use Tests\TestCase;

class TipoComprobanteControllerTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesContabilidadFixtures;
    use RefreshDatabase;

    public function test_crea_un_tipo_de_comprobante(): void
    {
        $this->actingAsAdmin();

        $respuesta = $this->postJson('/api/v1/contabilidad/tipos-comprobante', ['codigo' => 'CI', 'nombre' => 'Comprobante de ingreso']);

        $respuesta->assertStatus(201);
        $this->assertDatabaseHas('tipos_comprobante', ['codigo' => 'CI', 'nombre' => 'Comprobante de ingreso', 'activo' => true]);
    }

    public function test_lista_los_tipos_de_comprobante(): void
    {
        $this->actingAsAdmin();
        $this->crearTipoComprobante();
        $this->crearTipoComprobante();

        $respuesta = $this->getJson('/api/v1/contabilidad/tipos-comprobante');

        $respuesta->assertStatus(200)->assertJsonCount(2, 'data');
    }

    public function test_sin_autenticar_no_puede_listar(): void
    {
        $this->getJson('/api/v1/contabilidad/tipos-comprobante')->assertStatus(401);
    }

    public function test_ve_el_detalle_de_un_tipo_de_comprobante(): void
    {
        $this->actingAsAdmin();
        $tipo = $this->crearTipoComprobante(['codigo' => 'CE', 'nombre' => 'Comprobante de egreso']);

        $respuesta = $this->getJson("/api/v1/contabilidad/tipos-comprobante/{$tipo->id}");

        $respuesta->assertStatus(200)->assertJsonPath('tipo.codigo', 'CE');
    }

    public function test_un_tipo_de_comprobante_inexistente_responde_404_al_ver(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/v1/contabilidad/tipos-comprobante/999999')->assertStatus(404);
    }

    public function test_edita_un_tipo_de_comprobante(): void
    {
        $this->actingAsAdmin();
        $tipo = $this->crearTipoComprobante();

        $respuesta = $this->patchJson("/api/v1/contabilidad/tipos-comprobante/{$tipo->id}", ['nombre' => 'Nuevo nombre', 'activo' => false]);

        $respuesta->assertStatus(200);
        $this->assertDatabaseHas('tipos_comprobante', ['id' => $tipo->id, 'nombre' => 'Nuevo nombre', 'activo' => false]);
    }

    public function test_editar_rechaza_un_codigo_duplicado_de_otro_tipo(): void
    {
        $this->actingAsAdmin();
        $this->crearTipoComprobante(['codigo' => 'RC']);
        $tipo = $this->crearTipoComprobante(['codigo' => 'CE']);

        $respuesta = $this->patchJson("/api/v1/contabilidad/tipos-comprobante/{$tipo->id}", ['codigo' => 'RC']);

        $respuesta->assertStatus(422)->assertJsonValidationErrors('codigo');
    }

    public function test_edita_la_configuracion_del_consecutivo(): void
    {
        $this->actingAsAdmin();
        $tipo = $this->crearTipoComprobante();

        $respuesta = $this->patchJson("/api/v1/contabilidad/tipos-comprobante/{$tipo->id}", [
            'consecutivo_inicial' => 100,
            'consecutivo_final' => 200,
            'tipo_numeracion' => 'manual',
            'reinicio_periodo' => 'anual',
        ]);

        $respuesta->assertStatus(200);
        $this->assertDatabaseHas('tipos_comprobante', [
            'id' => $tipo->id,
            'consecutivo_inicial' => 100,
            'consecutivo_final' => 200,
            'tipo_numeracion' => 'manual',
            'reinicio_periodo' => 'anual',
        ]);
    }

    public function test_no_se_puede_cambiar_el_consecutivo_inicial_con_comprobantes_asociados(): void
    {
        $this->actingAsAdmin();
        $tipo = $this->crearTipoComprobante(['consecutivo_inicial' => 1]);
        $debito = $this->crearCuenta();
        $credito = $this->crearCuenta();

        $this->postJson('/api/v1/contabilidad/comprobantes', [
            'tipo_comprobante_id' => $tipo->id,
            'fecha' => now()->toDateString(),
            'concepto' => 'Comprobante de prueba',
            'lineas' => [
                ['cuenta_id' => $debito->id, 'debito' => 100, 'credito' => 0],
                ['cuenta_id' => $credito->id, 'debito' => 0, 'credito' => 100],
            ],
        ])->assertCreated();

        $respuesta = $this->patchJson("/api/v1/contabilidad/tipos-comprobante/{$tipo->id}", ['consecutivo_inicial' => 50]);

        $respuesta->assertStatus(422)->assertJsonValidationErrors('consecutivo_inicial');
        $this->assertDatabaseHas('tipos_comprobante', ['id' => $tipo->id, 'consecutivo_inicial' => 1]);
    }

    public function test_el_consecutivo_final_no_puede_quedar_por_debajo_del_inicial(): void
    {
        $this->actingAsAdmin();
        $tipo = $this->crearTipoComprobante(['consecutivo_inicial' => 10]);

        $respuesta = $this->patchJson("/api/v1/contabilidad/tipos-comprobante/{$tipo->id}", ['consecutivo_final' => 5]);

        $respuesta->assertStatus(422)->assertJsonValidationErrors('consecutivo_final');
    }

    public function test_un_tipo_de_comprobante_inexistente_responde_404_al_editar(): void
    {
        $this->actingAsAdmin();

        $this->patchJson('/api/v1/contabilidad/tipos-comprobante/999999', ['nombre' => 'X'])->assertStatus(404);
    }

    public function test_sin_permiso_no_puede_editar(): void
    {
        $admin = $this->actingAsAdmin();
        $tipo = $this->crearTipoComprobante();
        $this->actingAsAsesor(Empresa::find($admin->empresa_id));

        $this->patchJson("/api/v1/contabilidad/tipos-comprobante/{$tipo->id}", ['nombre' => 'X'])->assertStatus(403);
    }

    public function test_elimina_un_tipo_de_comprobante(): void
    {
        $this->actingAsAdmin();
        $tipo = $this->crearTipoComprobante();

        $this->deleteJson("/api/v1/contabilidad/tipos-comprobante/{$tipo->id}")->assertStatus(204);

        $this->assertSoftDeleted('tipos_comprobante', ['id' => $tipo->id]);
    }

    public function test_no_se_puede_eliminar_un_tipo_con_comprobantes_asociados(): void
    {
        $this->actingAsAdmin();
        $tipo = $this->crearTipoComprobante();
        // Códigos explícitos: el contador por defecto de crearCuenta() puede caer en un prefijo real de
        // cartera (1305/2205/2335) y esta prueba no tiene nada que ver con eso.
        $debito = $this->crearCuenta(['codigo' => '910001']);
        $credito = $this->crearCuenta(['codigo' => '910002']);

        $this->postJson('/api/v1/contabilidad/comprobantes', [
            'tipo_comprobante_id' => $tipo->id,
            'fecha' => now()->toDateString(),
            'concepto' => 'Comprobante de prueba',
            'lineas' => [
                ['cuenta_id' => $debito->id, 'debito' => 100, 'credito' => 0, 'descripcion' => 'Línea débito'],
                ['cuenta_id' => $credito->id, 'debito' => 0, 'credito' => 100, 'descripcion' => 'Línea crédito'],
            ],
        ])->assertCreated();

        $respuesta = $this->deleteJson("/api/v1/contabilidad/tipos-comprobante/{$tipo->id}");

        $respuesta->assertStatus(422)->assertJsonValidationErrors('tipo_comprobante');
        $this->assertDatabaseHas('tipos_comprobante', ['id' => $tipo->id, 'borrado_en' => null]);
    }

    public function test_no_se_puede_eliminar_un_tipo_en_uso_por_una_regla_de_contabilizacion(): void
    {
        $this->actingAsAdmin();
        $tipo = $this->crearTipoComprobante();
        $this->crearRegla(EventoContable::DesembolsoCredito, tipo: $tipo);

        $respuesta = $this->deleteJson("/api/v1/contabilidad/tipos-comprobante/{$tipo->id}");

        $respuesta->assertStatus(422)->assertJsonValidationErrors('tipo_comprobante');
        $this->assertDatabaseHas('tipos_comprobante', ['id' => $tipo->id, 'borrado_en' => null]);
    }

    public function test_no_se_puede_eliminar_un_tipo_en_uso_por_una_parametrizacion_de_gasto(): void
    {
        $this->actingAsAdmin();
        $tipo = $this->crearTipoComprobante();
        $tipoGasto = TipoGasto::create(['codigo' => 'ARR', 'nombre' => 'Arrendamiento']);
        $cuenta = $this->crearCuenta();

        ParametrizacionTipoGasto::create([
            'tipo_gasto_id' => $tipoGasto->id,
            'fecha_desde' => now()->toDateString(),
            'tipo_comprobante_id' => $tipo->id,
            'cuenta_gasto_id' => $cuenta->id,
            'cuenta_cxp_id' => $cuenta->id,
            'aplica_retefuente' => false,
            'aplica_reteica' => false,
            'aplica_reteiva' => false,
        ]);

        $respuesta = $this->deleteJson("/api/v1/contabilidad/tipos-comprobante/{$tipo->id}");

        $respuesta->assertStatus(422)->assertJsonValidationErrors('tipo_comprobante');
        $this->assertDatabaseHas('tipos_comprobante', ['id' => $tipo->id, 'borrado_en' => null]);
    }

    public function test_un_tipo_de_comprobante_inexistente_responde_404_al_eliminar(): void
    {
        $this->actingAsAdmin();

        $this->deleteJson('/api/v1/contabilidad/tipos-comprobante/999999')->assertStatus(404);
    }

    public function test_sin_permiso_no_puede_eliminar(): void
    {
        $admin = $this->actingAsAdmin();
        $tipo = $this->crearTipoComprobante();
        $this->actingAsAsesor(Empresa::find($admin->empresa_id));

        $this->deleteJson("/api/v1/contabilidad/tipos-comprobante/{$tipo->id}")->assertStatus(403);
    }

    public function test_un_admin_no_puede_ver_un_tipo_de_comprobante_de_otra_empresa(): void
    {
        $this->actingAsAdmin();
        $tipoAjeno = $this->crearTipoComprobante();

        $this->actingAsAdmin();

        $this->getJson("/api/v1/contabilidad/tipos-comprobante/{$tipoAjeno->id}")->assertStatus(404);
    }

    public function test_un_admin_no_puede_editar_un_tipo_de_comprobante_de_otra_empresa(): void
    {
        $this->actingAsAdmin();
        $tipoAjeno = $this->crearTipoComprobante();

        $this->actingAsAdmin();

        $respuesta = $this->patchJson("/api/v1/contabilidad/tipos-comprobante/{$tipoAjeno->id}", ['nombre' => 'Intento de edición ajena']);

        $respuesta->assertStatus(404);
    }

    public function test_un_admin_no_puede_eliminar_un_tipo_de_comprobante_de_otra_empresa(): void
    {
        $this->actingAsAdmin();
        $tipoAjeno = $this->crearTipoComprobante();

        $this->actingAsAdmin();

        $respuesta = $this->deleteJson("/api/v1/contabilidad/tipos-comprobante/{$tipoAjeno->id}");

        $respuesta->assertStatus(404);
        $this->assertDatabaseHas('tipos_comprobante', ['id' => $tipoAjeno->id, 'borrado_en' => null]);
    }
}
