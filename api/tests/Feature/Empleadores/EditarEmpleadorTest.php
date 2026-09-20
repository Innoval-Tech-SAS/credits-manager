<?php

namespace Tests\Feature\Empleadores;

use App\Usuarios\Models\Permiso;
use App\Usuarios\Models\Rol;
use App\Usuarios\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesCreditFixtures;
use Tests\TestCase;

class EditarEmpleadorTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesCreditFixtures;
    use RefreshDatabase;

    private function actingAsUsuarioConPermisoClientesEditar(): Usuario
    {
        $empresa = $this->crearEmpresaDePrueba();
        $rol = Rol::create(['empresa_id' => $empresa->id, 'nombre' => 'asesor_test']);
        $permiso = Permiso::firstOrCreate(['modulo' => 'clientes', 'accion' => 'editar']);
        $rol->permisos()->attach($permiso->id);

        $usuario = Usuario::create([
            'empresa_id' => $empresa->id,
            'nombre' => 'Asesor de prueba',
            'usuario' => 'asesor_test_'.uniqid(),
            'password_hash' => 'irrelevante-en-tests',
            'rol_id' => $rol->id,
            'activo' => true,
        ]);

        $this->actingAs($usuario, 'sanctum');

        return $usuario;
    }

    public function test_edita_un_empleador(): void
    {
        $this->actingAsAdmin();
        $empleador = $this->crearEmpleador(['nombre' => 'Nombre viejo']);

        $respuesta = $this->patchJson("/api/v1/empleadores/{$empleador->id}", [
            'nombre' => 'Nombre nuevo',
            'nit' => '900123456-1',
            'telefono' => '3000000000',
        ]);

        $respuesta->assertStatus(200);
        $this->assertDatabaseHas('empleadores', ['id' => $empleador->id, 'nombre' => 'Nombre nuevo', 'nit' => '900123456-1']);
    }

    public function test_rechaza_nombre_duplicado_de_otro_empleador(): void
    {
        $this->actingAsAdmin();
        $this->crearEmpleador(['nombre' => 'Ya existe']);
        $empleador = $this->crearEmpleador(['nombre' => 'Otro nombre']);

        $respuesta = $this->patchJson("/api/v1/empleadores/{$empleador->id}", ['nombre' => 'Ya existe']);

        $respuesta->assertStatus(422)->assertJsonValidationErrors('nombre');
    }

    public function test_un_usuario_con_permiso_de_editar_clientes_puede_editar_empleadores(): void
    {
        $this->actingAsUsuarioConPermisoClientesEditar();
        $empleador = $this->crearEmpleador();

        $respuesta = $this->patchJson("/api/v1/empleadores/{$empleador->id}", ['nombre' => 'Editado por asesor']);

        $respuesta->assertStatus(200);
    }

    public function test_asesor_sin_permiso_no_puede_editar_empleadores(): void
    {
        $this->actingAsAsesor();
        $empleador = $this->crearEmpleador();

        $respuesta = $this->patchJson("/api/v1/empleadores/{$empleador->id}", ['nombre' => 'Cualquiera']);

        $respuesta->assertStatus(403);
    }

    public function test_empleador_inexistente_devuelve_404(): void
    {
        $this->actingAsAdmin();

        $respuesta = $this->patchJson('/api/v1/empleadores/999999', ['nombre' => 'Cualquiera']);

        $respuesta->assertStatus(404);
    }

    public function test_sin_autenticar_no_puede_editar(): void
    {
        $this->actingAsAdmin();
        $empleador = $this->crearEmpleador();
        $this->salirDeSesion();

        $respuesta = $this->patchJson("/api/v1/empleadores/{$empleador->id}", ['nombre' => 'Cualquiera']);

        $respuesta->assertStatus(401);
    }
}
