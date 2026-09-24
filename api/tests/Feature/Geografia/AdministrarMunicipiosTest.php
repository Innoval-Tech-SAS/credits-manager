<?php

namespace Tests\Feature\Geografia;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\AfirmaAuditoria;
use Tests\Concerns\CreatesGeografiaFixtures;
use Tests\TestCase;

class AdministrarMunicipiosTest extends TestCase
{
    use ActsAsAdmin;
    use AfirmaAuditoria;
    use CreatesGeografiaFixtures;
    use RefreshDatabase;

    public function test_sin_autenticar_no_administra_municipios(): void
    {
        $municipio = $this->crearMunicipio();

        $this->postJson('/api/v1/geografia/municipios', [])->assertStatus(401);
        $this->patchJson("/api/v1/geografia/municipios/{$municipio->id}", [])->assertStatus(401);
    }

    public function test_un_admin_de_empresa_no_puede_administrar_municipios(): void
    {
        $estado = $this->crearEstado();
        $municipio = $this->crearMunicipio();

        $this->actingAsAdmin();

        $this->postJson('/api/v1/geografia/municipios', ['estado_id' => $estado->id, 'codigo' => '99999', 'nombre' => 'Nuevo'])->assertStatus(403);
        $this->patchJson("/api/v1/geografia/municipios/{$municipio->id}", ['nombre' => 'Otro'])->assertStatus(403);
        $this->assertDatabaseMissing('municipios', ['codigo' => '99999']);
    }

    public function test_crear_exige_los_campos_y_un_estado_activo(): void
    {
        $this->actingAsSuperAdmin();
        $inactivo = $this->crearEstado(['activo' => false]);

        $this->postJson('/api/v1/geografia/municipios', [])->assertStatus(422)->assertJsonValidationErrors(['estado_id', 'codigo', 'nombre']);
        $this->postJson('/api/v1/geografia/municipios', ['estado_id' => 9999, 'codigo' => '1', 'nombre' => 'X'])->assertStatus(422)->assertJsonValidationErrors(['estado_id']);
        $this->postJson('/api/v1/geografia/municipios', ['estado_id' => $inactivo->id, 'codigo' => '1', 'nombre' => 'X'])->assertStatus(422)->assertJsonValidationErrors(['estado_id']);
    }

    public function test_el_codigo_es_unico_dentro_del_estado_pero_se_puede_repetir_en_otro(): void
    {
        $this->actingAsSuperAdmin();
        $estado = $this->crearEstado();
        $otroEstado = $this->crearEstado();
        $this->crearMunicipio(['estado_id' => $estado->id, 'codigo' => '47001']);

        $this->postJson('/api/v1/geografia/municipios', ['estado_id' => $estado->id, 'codigo' => '47001', 'nombre' => 'Duplicado'])
            ->assertStatus(422)->assertJsonValidationErrors(['codigo']);

        $this->postJson('/api/v1/geografia/municipios', ['estado_id' => $otroEstado->id, 'codigo' => '47001', 'nombre' => 'En otro estado'])
            ->assertCreated();
    }

    public function test_el_superadmin_agrega_un_municipio_y_queda_auditado(): void
    {
        $superadmin = $this->actingAsSuperAdmin();
        $estado = $this->crearEstado(['nombre' => 'Magdalena']);

        $respuesta = $this->postJson('/api/v1/geografia/municipios', [
            'estado_id' => $estado->id, 'codigo' => '47999', 'nombre' => 'Municipio nuevo', 'es_capital' => false,
        ])->assertCreated();

        $this->assertDatabaseHas('municipios', ['id' => $respuesta->json('municipio.id'), 'estado_id' => $estado->id, 'codigo' => '47999', 'nombre' => 'Municipio nuevo', 'activo' => true]);
        $this->assertSame('Magdalena', $respuesta->json('municipio.estado.nombre'));

        $evento = $this->assertEventoAuditoria('crear', 'geografia', 'municipio', $respuesta->json('municipio.id'));
        $this->assertNull($evento['empresa_id']);
        $this->assertSame($superadmin->id, $evento['autor']['usuario_id']);
    }

    public function test_editar_un_municipio_inexistente_da_404(): void
    {
        $this->actingAsSuperAdmin();

        $this->patchJson('/api/v1/geografia/municipios/9999', ['nombre' => 'X'])->assertStatus(404);
    }

    public function test_editar_valida_el_codigo_repetido_en_su_estado(): void
    {
        $this->actingAsSuperAdmin();
        $estado = $this->crearEstado();
        $this->crearMunicipio(['estado_id' => $estado->id, 'codigo' => '11111']);
        $municipio = $this->crearMunicipio(['estado_id' => $estado->id, 'codigo' => '22222']);

        $this->patchJson("/api/v1/geografia/municipios/{$municipio->id}", ['codigo' => '11111'])->assertStatus(422)->assertJsonValidationErrors(['codigo']);
        $this->patchJson("/api/v1/geografia/municipios/{$municipio->id}", ['codigo' => '22222', 'nombre' => 'Mismo código'])->assertOk();
        $this->patchJson("/api/v1/geografia/municipios/{$municipio->id}", ['activo' => 'quizás'])->assertStatus(422)->assertJsonValidationErrors(['activo']);
    }

    public function test_el_superadmin_edita_y_desactiva_un_municipio_y_queda_auditado(): void
    {
        $this->actingAsSuperAdmin();
        $municipio = $this->crearMunicipio(['nombre' => 'Nombre viejo']);

        $this->patchJson("/api/v1/geografia/municipios/{$municipio->id}", ['nombre' => 'Nombre nuevo', 'activo' => false])->assertOk();

        $this->assertDatabaseHas('municipios', ['id' => $municipio->id, 'nombre' => 'Nombre nuevo', 'activo' => false]);

        $evento = $this->assertEventoAuditoria('modificar', 'geografia', 'municipio', $municipio->id);
        $this->assertSame(['nombre' => 'Nombre viejo', 'activo' => true], $evento['valores_anteriores']);
        $this->assertSame(['nombre' => 'Nombre nuevo', 'activo' => false], $evento['valores_nuevos']);
    }

    public function test_un_municipio_desactivado_deja_de_ofrecerse_a_las_empresas(): void
    {
        $estado = $this->crearEstado();
        $municipio = $this->crearMunicipio(['estado_id' => $estado->id, 'activo' => false]);

        $this->actingAsAdmin();
        $this->getJson("/api/v1/geografia/municipios?estado_id={$estado->id}")->assertOk()->assertJsonCount(0, 'municipios');

        $this->actingAsSuperAdmin();
        $this->getJson("/api/v1/geografia/municipios?estado_id={$estado->id}")->assertOk()->assertJsonPath('municipios.0.id', $municipio->id);
    }
}
