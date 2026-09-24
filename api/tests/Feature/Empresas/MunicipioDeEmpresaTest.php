<?php

namespace Tests\Feature\Empresas;

use App\Auditoria\Constants\AuditoriaConstants;
use App\Empresas\Models\Empresa;
use Database\Seeders\PermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesGeografiaFixtures;
use Tests\TestCase;

class MunicipioDeEmpresaTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesGeografiaFixtures;
    use RefreshDatabase;

    private function datosEdicion(Empresa $empresa, array $cambios = []): array
    {
        return array_merge([
            'nombre_empresa' => $empresa->nombre_empresa,
            'nit' => $empresa->nit,
            'direccion' => $empresa->direccion,
            'municipio_id' => $empresa->municipio_id,
            'telefono' => $empresa->telefono,
            'correo' => $empresa->correo,
        ], $cambios);
    }

    public function test_crear_empresa_exige_un_municipio_existente_y_activo(): void
    {
        $this->actingAsSuperAdmin();
        $inactivo = $this->crearMunicipio(['activo' => false]);
        $datos = [
            'nombre_empresa' => 'Empresa nueva SAS', 'nit' => '900123456-7', 'direccion' => 'Calle 1', 'telefono' => '3000000000',
            'correo' => 'contacto@empresanueva.com', 'admin_nombre' => 'Admin Nuevo', 'admin_usuario' => 'admin_nuevo', 'admin_password' => 'Clave123!',
        ];

        $this->postJson('/api/v1/empresas', $datos)->assertStatus(422)->assertJsonValidationErrors(['municipio_id']);
        $this->postJson('/api/v1/empresas', [...$datos, 'municipio_id' => 9999])->assertStatus(422)->assertJsonValidationErrors(['municipio_id']);
        $this->postJson('/api/v1/empresas', [...$datos, 'municipio_id' => $inactivo->id])->assertStatus(422)->assertJsonValidationErrors(['municipio_id']);
        $this->assertDatabaseMissing('empresas', ['nombre_empresa' => 'Empresa nueva SAS']);
    }

    public function test_el_superadmin_crea_una_empresa_con_su_municipio_y_queda_en_la_bitacora(): void
    {
        $this->seed(PermisoSeeder::class);
        $this->actingAsSuperAdmin();
        $municipio = $this->crearMunicipio(['nombre' => 'Barranquilla']);

        $respuesta = $this->postJson('/api/v1/empresas', [
            'nombre_empresa' => 'Empresa nueva SAS', 'nit' => '900123456-7', 'direccion' => 'Calle 1', 'municipio_id' => $municipio->id,
            'telefono' => '3000000000', 'correo' => 'contacto@empresanueva.com', 'admin_nombre' => 'Admin Nuevo', 'admin_usuario' => 'admin_nuevo', 'admin_password' => 'Clave123!',
        ])->assertCreated();

        $this->assertSame('Barranquilla', $respuesta->json('empresa.municipio.nombre'));
        $this->assertDatabaseHas('empresas', ['id' => $respuesta->json('empresa.id'), 'municipio_id' => $municipio->id]);

        $evento = $this->redis->payloads(AuditoriaConstants::STREAM)[0];
        $this->assertSame($municipio->id, $evento['valores_nuevos']['municipio_id']);
    }

    public function test_editar_empresa_exige_un_municipio_activo(): void
    {
        $this->actingAsSuperAdmin();
        $empresa = $this->crearEmpresaDePrueba();
        $inactivo = $this->crearMunicipio(['activo' => false]);

        $sinMunicipio = $this->datosEdicion($empresa);
        unset($sinMunicipio['municipio_id']);

        $this->patchJson("/api/v1/empresas/{$empresa->id}", $sinMunicipio)->assertStatus(422)->assertJsonValidationErrors(['municipio_id']);
        $this->patchJson("/api/v1/empresas/{$empresa->id}", $this->datosEdicion($empresa, ['municipio_id' => $inactivo->id]))
            ->assertStatus(422)->assertJsonValidationErrors(['municipio_id']);
    }

    public function test_el_admin_cambia_el_municipio_de_su_empresa_y_la_respuesta_trae_estado_y_pais(): void
    {
        $admin = $this->actingAsAdmin();
        $empresa = Empresa::find($admin->empresa_id);
        $nuevo = $this->crearMunicipio(['nombre' => 'Cartagena']);

        $respuesta = $this->patchJson('/api/v1/empresa', $this->datosEdicion($empresa, ['municipio_id' => $nuevo->id]))->assertOk();

        $this->assertDatabaseHas('empresas', ['id' => $empresa->id, 'municipio_id' => $nuevo->id]);
        $this->assertSame('Cartagena', $respuesta->json('empresa.municipio.nombre'));
        $this->assertSame($nuevo->estado_id, $respuesta->json('empresa.municipio.estado.id'));
        $this->assertSame($nuevo->estado->pais_id, $respuesta->json('empresa.municipio.estado.pais_id'));

        $evento = $this->redis->payloads(AuditoriaConstants::STREAM)[0];
        $this->assertSame(['municipio_id' => $nuevo->id], $evento['valores_nuevos']);
    }

    public function test_la_empresa_propia_se_consulta_con_su_municipio(): void
    {
        $admin = $this->actingAsAdmin();

        $this->getJson('/api/v1/empresa')->assertOk()
            ->assertJsonPath('empresa.id', $admin->empresa_id)
            ->assertJsonPath('empresa.municipio.nombre', 'Santa Marta')
            ->assertJsonPath('empresa.municipio.estado.nombre', 'Magdalena');
    }

    public function test_el_listado_del_superadmin_trae_el_municipio_de_cada_empresa(): void
    {
        $this->actingAsSuperAdmin();
        $this->crearEmpresaDePrueba();

        $this->getJson('/api/v1/empresas')->assertOk()->assertJsonPath('data.0.municipio.nombre', 'Santa Marta');
    }

    public function test_un_admin_no_edita_una_empresa_ajena_al_cambiar_de_municipio(): void
    {
        $this->actingAsAdmin();
        $ajena = $this->crearEmpresaDePrueba();
        $municipio = $this->crearMunicipio();

        $this->patchJson("/api/v1/empresas/{$ajena->id}", $this->datosEdicion($ajena, ['municipio_id' => $municipio->id]))->assertStatus(403);
        $this->assertDatabaseHas('empresas', ['id' => $ajena->id, 'municipio_id' => $ajena->municipio_id]);
    }
}
