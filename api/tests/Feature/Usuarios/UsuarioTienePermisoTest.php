<?php

namespace Tests\Feature\Usuarios;

use App\Usuarios\Models\Permiso;
use App\Usuarios\Models\Rol;
use App\Usuarios\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\TestCase;

class UsuarioTienePermisoTest extends TestCase
{
    use ActsAsAdmin;
    use RefreshDatabase;

    private function crearUsuarioConRol(array $permisosDelRol): Usuario
    {
        $empresa = $this->crearEmpresaDePrueba();

        $rol = Rol::create(['empresa_id' => $empresa->id, 'nombre' => 'asesor_test']);

        foreach ($permisosDelRol as [$modulo, $accion]) {
            $permiso = Permiso::create(['modulo' => $modulo, 'accion' => $accion]);
            $rol->permisos()->attach($permiso->id);
        }

        return Usuario::create([
            'empresa_id' => $empresa->id,
            'nombre' => 'Usuario de prueba',
            'usuario' => 'usuario_test_'.uniqid(),
            'password_hash' => 'irrelevante-en-tests',
            'rol_id' => $rol->id,
            'activo' => true,
        ]);
    }

    public function test_sin_excepcion_usa_el_permiso_del_rol(): void
    {
        $usuario = $this->crearUsuarioConRol([['clientes', 'crear']]);

        $this->assertTrue($usuario->tienePermiso('clientes', 'crear'));
        $this->assertFalse($usuario->tienePermiso('contabilidad', 'listar'));
    }

    public function test_excepcion_revoca_un_permiso_que_el_rol_si_daria(): void
    {
        $usuario = $this->crearUsuarioConRol([['clientes', 'crear']]);
        $permiso = Permiso::where('modulo', 'clientes')->where('accion', 'crear')->firstOrFail();

        $usuario->permisosExtra()->attach($permiso->id, ['concedido' => false]);

        $this->assertFalse($usuario->tienePermiso('clientes', 'crear'));
    }

    public function test_excepcion_concede_un_permiso_que_el_rol_no_daria(): void
    {
        $usuario = $this->crearUsuarioConRol([['clientes', 'crear']]);
        $permiso = Permiso::create(['modulo' => 'contabilidad', 'accion' => 'listar']);

        $usuario->permisosExtra()->attach($permiso->id, ['concedido' => true]);

        $this->assertTrue($usuario->tienePermiso('contabilidad', 'listar'));
    }
}
