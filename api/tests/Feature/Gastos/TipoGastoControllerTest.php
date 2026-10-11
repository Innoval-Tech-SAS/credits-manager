<?php

namespace Tests\Feature\Gastos;

use App\Empresas\Models\Empresa;
use App\Gastos\Models\Gasto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesContabilidadFixtures;
use Tests\Concerns\CreatesGastosFixtures;
use Tests\TestCase;

class TipoGastoControllerTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesContabilidadFixtures;
    use CreatesGastosFixtures;
    use RefreshDatabase;

    public function test_crea_un_tipo_de_gasto(): void
    {
        $this->actingAsAdmin();

        $respuesta = $this->postJson('/api/v1/gastos/tipos-gasto', [
            'codigo' => 'ARR',
            'nombre' => 'Arriendo',
            'requiere_tercero' => true,
            'aplica_iva' => false,
        ]);

        $respuesta->assertStatus(201)->assertJsonPath('tipo_gasto.codigo', 'ARR');
        $this->assertDatabaseHas('tipos_gasto', ['codigo' => 'ARR', 'nombre' => 'Arriendo']);
    }

    public function test_rechaza_codigo_duplicado_en_la_misma_empresa(): void
    {
        $this->actingAsAdmin();
        $this->crearTipoGasto(['codigo' => 'ARR']);

        $respuesta = $this->postJson('/api/v1/gastos/tipos-gasto', ['codigo' => 'ARR', 'nombre' => 'Arriendo duplicado']);

        $respuesta->assertStatus(422)->assertJsonValidationErrors('codigo');
    }

    public function test_lista_los_tipos_de_gasto(): void
    {
        $this->actingAsAdmin();
        $this->crearTipoGasto();
        $this->crearTipoGasto();

        $respuesta = $this->getJson('/api/v1/gastos/tipos-gasto');

        $respuesta->assertStatus(200)->assertJsonCount(2, 'tipos_gasto');
    }

    public function test_sin_autenticar_no_puede_listar(): void
    {
        $respuesta = $this->getJson('/api/v1/gastos/tipos-gasto');

        $respuesta->assertStatus(401);
    }

    public function test_asesor_sin_permiso_no_puede_crear(): void
    {
        $this->actingAsAsesor();

        $respuesta = $this->postJson('/api/v1/gastos/tipos-gasto', ['codigo' => 'ARR', 'nombre' => 'Arriendo']);

        $respuesta->assertStatus(403);
    }

    public function test_ve_el_detalle_de_un_tipo_de_gasto(): void
    {
        $this->actingAsAdmin();
        $tipoGasto = $this->crearTipoGasto(['codigo' => 'ARR', 'nombre' => 'Arriendo']);

        $respuesta = $this->getJson("/api/v1/gastos/tipos-gasto/{$tipoGasto->id}");

        $respuesta->assertStatus(200)->assertJsonPath('tipo_gasto.codigo', 'ARR');
    }

    public function test_un_tipo_de_gasto_inexistente_responde_404_al_ver(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/v1/gastos/tipos-gasto/999999')->assertStatus(404);
    }

    public function test_desactiva_un_tipo_de_gasto(): void
    {
        $this->actingAsAdmin();
        $tipoGasto = $this->crearTipoGasto();

        $respuesta = $this->patchJson("/api/v1/gastos/tipos-gasto/{$tipoGasto->id}", ['activo' => false]);

        $respuesta->assertStatus(200)->assertJsonPath('tipo_gasto.activo', false);
    }

    public function test_editar_sin_cambiar_el_codigo_no_falla_por_duplicado(): void
    {
        $this->actingAsAdmin();
        $tipoGasto = $this->crearTipoGasto(['codigo' => 'ARR', 'nombre' => 'Arriendo']);

        $respuesta = $this->patchJson("/api/v1/gastos/tipos-gasto/{$tipoGasto->id}", [
            'codigo' => 'ARR',
            'nombre' => 'Arriendo actualizado',
        ]);

        $respuesta->assertStatus(200)->assertJsonPath('tipo_gasto.nombre', 'Arriendo actualizado');
    }

    public function test_editar_rechaza_un_codigo_duplicado_de_otro_tipo(): void
    {
        $this->actingAsAdmin();
        $this->crearTipoGasto(['codigo' => 'ARR']);
        $tipoGasto = $this->crearTipoGasto(['codigo' => 'SRV']);

        $respuesta = $this->patchJson("/api/v1/gastos/tipos-gasto/{$tipoGasto->id}", ['codigo' => 'ARR']);

        $respuesta->assertStatus(422)->assertJsonValidationErrors('codigo');
    }

    public function test_un_tipo_de_gasto_inexistente_responde_404_al_editar(): void
    {
        $this->actingAsAdmin();

        $this->patchJson('/api/v1/gastos/tipos-gasto/999999', ['nombre' => 'X'])->assertStatus(404);
    }

    public function test_sin_permiso_no_puede_editar(): void
    {
        $admin = $this->actingAsAdmin();
        $tipoGasto = $this->crearTipoGasto();
        $this->actingAsAsesor(Empresa::find($admin->empresa_id));

        $this->patchJson("/api/v1/gastos/tipos-gasto/{$tipoGasto->id}", ['nombre' => 'X'])->assertStatus(403);
    }

    public function test_elimina_un_tipo_de_gasto(): void
    {
        $this->actingAsAdmin();
        $tipoGasto = $this->crearTipoGasto();

        $this->deleteJson("/api/v1/gastos/tipos-gasto/{$tipoGasto->id}")->assertStatus(204);

        $this->assertSoftDeleted('tipos_gasto', ['id' => $tipoGasto->id]);
    }

    public function test_no_se_puede_eliminar_un_tipo_con_gastos_asociados(): void
    {
        $this->actingAsAdmin();
        $tipoGasto = $this->crearTipoGasto();
        $parametrizacion = $this->crearParametrizacion($tipoGasto);

        Gasto::create([
            'tipo_gasto_id' => $tipoGasto->id,
            'fecha' => now()->toDateString(),
            'numero_documento' => 'FAC-001',
            'concepto' => 'Gasto de prueba',
            'valor_bruto' => 100000,
            'valor_neto' => 100000,
            'parametrizacion_tipo_gasto_id' => $parametrizacion->id,
        ]);

        $respuesta = $this->deleteJson("/api/v1/gastos/tipos-gasto/{$tipoGasto->id}");

        $respuesta->assertStatus(422)->assertJsonValidationErrors('tipo_gasto');
        $this->assertDatabaseHas('tipos_gasto', ['id' => $tipoGasto->id, 'borrado_en' => null]);
    }

    public function test_no_se_puede_eliminar_un_tipo_en_uso_por_una_parametrizacion(): void
    {
        $this->actingAsAdmin();
        $tipoGasto = $this->crearTipoGasto();
        $this->crearParametrizacion($tipoGasto);

        $respuesta = $this->deleteJson("/api/v1/gastos/tipos-gasto/{$tipoGasto->id}");

        $respuesta->assertStatus(422)->assertJsonValidationErrors('tipo_gasto');
        $this->assertDatabaseHas('tipos_gasto', ['id' => $tipoGasto->id, 'borrado_en' => null]);
    }

    public function test_un_tipo_de_gasto_inexistente_responde_404_al_eliminar(): void
    {
        $this->actingAsAdmin();

        $this->deleteJson('/api/v1/gastos/tipos-gasto/999999')->assertStatus(404);
    }

    public function test_sin_permiso_no_puede_eliminar(): void
    {
        $admin = $this->actingAsAdmin();
        $tipoGasto = $this->crearTipoGasto();
        $this->actingAsAsesor(Empresa::find($admin->empresa_id));

        $this->deleteJson("/api/v1/gastos/tipos-gasto/{$tipoGasto->id}")->assertStatus(403);
    }

    public function test_un_admin_no_puede_ver_un_tipo_de_gasto_de_otra_empresa(): void
    {
        $this->actingAsAdmin();
        $tipoAjeno = $this->crearTipoGasto();

        $this->actingAsAdmin();

        $this->getJson("/api/v1/gastos/tipos-gasto/{$tipoAjeno->id}")->assertStatus(404);
    }

    public function test_un_admin_no_puede_editar_un_tipo_de_gasto_de_otra_empresa(): void
    {
        $this->actingAsAdmin();
        $tipoAjeno = $this->crearTipoGasto();

        $this->actingAsAdmin();

        $respuesta = $this->patchJson("/api/v1/gastos/tipos-gasto/{$tipoAjeno->id}", ['nombre' => 'Intento de edición ajena']);

        $respuesta->assertStatus(404);
    }

    public function test_un_admin_no_puede_eliminar_un_tipo_de_gasto_de_otra_empresa(): void
    {
        $this->actingAsAdmin();
        $tipoAjeno = $this->crearTipoGasto();

        $this->actingAsAdmin();

        $respuesta = $this->deleteJson("/api/v1/gastos/tipos-gasto/{$tipoAjeno->id}");

        $respuesta->assertStatus(404);
        $this->assertDatabaseHas('tipos_gasto', ['id' => $tipoAjeno->id, 'borrado_en' => null]);
    }
}
