<?php

namespace Tests\Feature\Usuarios;

use App\Usuarios\Constants\UsuarioConstants;
use App\Usuarios\Models\Rol;
use App\Usuarios\Repositories\RolRepository;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\TestCase;

class RolSuperAdminTest extends TestCase
{
    use ActsAsAdmin;
    use RefreshDatabase;

    public function test_es_superadmin_lo_da_el_rol(): void
    {
        $this->assertTrue($this->crearSuperAdmin()->es_superadmin);
        $this->assertFalse($this->actingAsAdmin()->es_superadmin);
    }

    public function test_la_respuesta_del_usuario_incluye_es_superadmin(): void
    {
        $this->actingAsSuperAdmin();

        $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('usuario.es_superadmin', true);
    }

    public function test_la_base_rechaza_un_segundo_super_admin(): void
    {
        $this->crearSuperAdmin();

        $this->expectException(QueryException::class);

        $this->crearSuperAdmin();
    }

    public function test_un_super_admin_borrado_libera_el_lugar(): void
    {
        $this->crearSuperAdmin()->delete();

        $this->assertTrue($this->crearSuperAdmin()->es_superadmin);
    }

    public function test_la_base_rechaza_un_super_admin_con_empresa(): void
    {
        $empresa = $this->crearEmpresaDePrueba();

        $this->expectExceptionMessage('El rol Super Admin no pertenece a ninguna empresa');

        $this->crearSuperAdmin(['empresa_id' => $empresa->id]);
    }

    public function test_la_base_rechaza_un_usuario_sin_empresa_con_otro_rol(): void
    {
        $rol = Rol::create(['empresa_id' => $this->crearEmpresaDePrueba()->id, 'nombre' => UsuarioConstants::ROL_ADMIN]);

        $this->expectExceptionMessage('Un usuario sin empresa solo puede tener el rol Super Admin');

        $this->crearSuperAdmin(['rol_id' => $rol->id]);
    }

    public function test_la_base_rechaza_un_segundo_rol_sin_empresa(): void
    {
        $this->expectException(QueryException::class);

        Rol::create(['nombre' => 'otro_rol_de_plataforma']);
    }

    public function test_una_empresa_no_puede_asignar_el_rol_super_admin(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/usuarios', [
            'nombre' => 'Intruso', 'usuario' => 'intruso_'.uniqid(), 'clave' => 'Clave123!',
            'rol_id' => app(RolRepository::class)->rolSuperAdmin()->id, 'activo' => true,
        ])->assertStatus(422)->assertJsonValidationErrors('rol_id');
    }

    public function test_una_empresa_no_ve_el_rol_super_admin(): void
    {
        $this->actingAsAdmin();

        $nombres = collect($this->getJson('/api/v1/roles')->json('data'))->pluck('nombre');

        $this->assertNotContains(UsuarioConstants::ROL_SUPERADMIN, $nombres);
    }
}
