<?php

namespace Tests\Feature\Usuarios;

use App\Usuarios\Constants\UsuarioConstants;
use App\Usuarios\Models\Rol;
use App\Usuarios\Models\Usuario;
use App\Usuarios\Services\RolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\ActsAsAdmin;
use Tests\TestCase;

class RolServiceTest extends TestCase
{
    use ActsAsAdmin;
    use RefreshDatabase;

    public function test_no_permite_eliminar_el_rol_admin(): void
    {
        $admin = Rol::create(['empresa_id' => $this->crearEmpresaDePrueba()->id, 'nombre' => UsuarioConstants::ROL_ADMIN]);

        $this->expectException(ValidationException::class);

        app(RolService::class)->eliminar($admin);
    }

    public function test_no_permite_eliminar_un_rol_con_usuarios_activos(): void
    {
        $empresa = $this->crearEmpresaDePrueba();
        $rol = Rol::create(['empresa_id' => $empresa->id, 'nombre' => 'contable_test']);
        Usuario::create([
            'empresa_id' => $empresa->id,
            'nombre' => 'Usuario de prueba',
            'usuario' => 'usuario_test_'.uniqid(),
            'password_hash' => 'irrelevante-en-tests',
            'rol_id' => $rol->id,
            'activo' => true,
        ]);

        $this->expectException(ValidationException::class);

        app(RolService::class)->eliminar($rol);
    }

    public function test_elimina_un_rol_sin_usuarios_activos(): void
    {
        $rol = Rol::create(['empresa_id' => $this->crearEmpresaDePrueba()->id, 'nombre' => 'consulta_test']);

        $this->assertTrue(app(RolService::class)->eliminar($rol));
        $this->assertSoftDeleted('roles', ['id' => $rol->id]);
    }

    public function test_no_permite_renombrar_el_rol_admin(): void
    {
        $admin = Rol::create(['empresa_id' => $this->crearEmpresaDePrueba()->id, 'nombre' => UsuarioConstants::ROL_ADMIN]);

        $this->expectException(ValidationException::class);

        app(RolService::class)->editar($admin, ['nombre' => 'superadmin']);
    }

    public function test_no_permite_eliminar_el_rol_asesor(): void
    {
        $asesor = Rol::create(['empresa_id' => $this->crearEmpresaDePrueba()->id, 'nombre' => UsuarioConstants::ROL_ASESOR]);

        $this->expectException(ValidationException::class);

        app(RolService::class)->eliminar($asesor);
    }

    public function test_no_permite_renombrar_el_rol_asesor(): void
    {
        $asesor = Rol::create(['empresa_id' => $this->crearEmpresaDePrueba()->id, 'nombre' => UsuarioConstants::ROL_ASESOR]);

        $this->expectException(ValidationException::class);

        app(RolService::class)->editar($asesor, ['nombre' => 'vendedor']);
    }

    public function test_permite_editar_la_descripcion_del_rol_asesor(): void
    {
        $this->actingAsAdmin();
        $asesor = Rol::create(['nombre' => UsuarioConstants::ROL_ASESOR]);

        $rol = app(RolService::class)->editar($asesor, ['nombre' => UsuarioConstants::ROL_ASESOR, 'descripcion' => 'Atiende clientes']);

        $this->assertSame('Atiende clientes', $rol->descripcion);
    }
}
