<?php

namespace Tests\Feature\Empresas;

use Database\Seeders\PermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\TestCase;

class EmpresaAdminControllerTest extends TestCase
{
    use ActsAsAdmin;
    use RefreshDatabase;

    private function datosEmpresaValidos(): array
    {
        return [
            'nombre_empresa' => 'Empresa nueva SAS',
            'nit' => '900123456-7',
            'direccion' => 'Calle 1 # 2-3',
            'municipio_id' => $this->municipioDePrueba()->id,
            'telefono' => '3000000000',
            'correo' => 'contacto@empresanueva.com',
            'admin_nombre' => 'Admin Nuevo',
            'admin_usuario' => 'admin_nuevo',
            'admin_password' => 'Clave123!',
        ];
    }

    public function test_un_admin_normal_no_puede_listar_empresas(): void
    {
        $this->actingAsAdmin();

        $respuesta = $this->getJson('/api/v1/empresas');

        $respuesta->assertStatus(403);
    }

    public function test_sin_autenticar_no_puede_listar_empresas(): void
    {
        $respuesta = $this->getJson('/api/v1/empresas');

        $respuesta->assertStatus(401);
    }

    public function test_superadmin_crea_una_empresa_con_su_primer_admin(): void
    {
        $this->actingAsSuperAdmin();
        $this->seed(PermisoSeeder::class);

        $respuesta = $this->postJson('/api/v1/empresas', $this->datosEmpresaValidos());

        $respuesta->assertStatus(201);
        $this->assertDatabaseHas('empresas', ['nombre_empresa' => 'Empresa nueva SAS']);
        $this->assertDatabaseHas('usuarios', ['usuario' => 'admin_nuevo']);
        $this->assertDatabaseHas('roles', ['nombre' => 'admin', 'empresa_id' => $respuesta->json('empresa.id')]);
    }

    public function test_superadmin_lista_y_edita_empresas(): void
    {
        $this->actingAsSuperAdmin();
        $this->seed(PermisoSeeder::class);
        $creada = $this->postJson('/api/v1/empresas', $this->datosEmpresaValidos())->json('empresa');

        $this->getJson('/api/v1/empresas')->assertStatus(200)->assertJsonCount(1, 'data');

        $respuesta = $this->patchJson("/api/v1/empresas/{$creada['id']}", [
            'nombre_empresa' => 'Empresa nueva SAS (renombrada)',
            'nit' => $creada['nit'],
            'direccion' => $creada['direccion'],
            'municipio_id' => $creada['municipio_id'],
            'telefono' => $creada['telefono'],
            'correo' => $creada['correo'],
        ]);

        $respuesta->assertStatus(200);
        $this->assertSame('Empresa nueva SAS (renombrada)', $respuesta->json('empresa.nombre_empresa'));
    }
}
