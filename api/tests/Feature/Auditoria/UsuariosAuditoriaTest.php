<?php

namespace Tests\Feature\Auditoria;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\TestCase;

class UsuariosAuditoriaTest extends TestCase
{
    use ActsAsAdmin;
    use RefreshDatabase;

    public function test_sin_autenticar_responde_401(): void
    {
        $this->getJson('/api/v1/auditoria/usuarios')->assertUnauthorized();
    }

    public function test_el_administrador_de_una_empresa_no_ve_el_catalogo_cruzado(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/v1/auditoria/usuarios')->assertForbidden();
    }

    public function test_el_superadmin_ve_usuarios_de_todas_las_empresas_con_su_empresa(): void
    {
        $empresaUno = $this->crearEmpresaDePrueba();
        $admin = $this->actingAsAdmin($empresaUno);

        $this->actingAsSuperAdmin();

        $respuesta = $this->getJson('/api/v1/auditoria/usuarios')->assertOk();

        $usuarios = collect($respuesta->json('usuarios'));
        $fila = $usuarios->firstWhere('id', $admin->id);

        $this->assertNotNull($fila);
        $this->assertSame($empresaUno->nombre_empresa, $fila['empresa']);
    }
}
