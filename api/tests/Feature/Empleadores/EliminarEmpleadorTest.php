<?php

namespace Tests\Feature\Empleadores;

use App\Usuarios\Models\Permiso;
use App\Usuarios\Models\Rol;
use App\Usuarios\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesCreditFixtures;
use Tests\TestCase;

class EliminarEmpleadorTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesCreditFixtures;
    use RefreshDatabase;

    public function test_admin_elimina_un_empleador_sin_clientes(): void
    {
        $this->actingAsAdmin();
        $empleador = $this->crearEmpleador();

        $respuesta = $this->deleteJson("/api/v1/empleadores/{$empleador->id}");

        $respuesta->assertStatus(204);
        $this->assertSoftDeleted('empleadores', ['id' => $empleador->id]);
    }

    public function test_rechaza_eliminar_un_empleador_con_clientes_asignados(): void
    {
        $this->actingAsAdmin();
        $empleador = $this->crearEmpleador();
        $this->crearCliente(['empleador_id' => $empleador->id]);

        $respuesta = $this->deleteJson("/api/v1/empleadores/{$empleador->id}");

        $respuesta->assertStatus(422)->assertJsonValidationErrors('empleador');
        $this->assertDatabaseHas('empleadores', ['id' => $empleador->id, 'borrado_en' => null]);
    }

    public function test_un_usuario_con_permiso_de_editar_clientes_no_puede_eliminar_empleadores(): void
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
        $empleador = $this->crearEmpleador();

        $respuesta = $this->deleteJson("/api/v1/empleadores/{$empleador->id}");

        $respuesta->assertStatus(403);
    }

    public function test_empleador_inexistente_devuelve_404(): void
    {
        $this->actingAsAdmin();

        $respuesta = $this->deleteJson('/api/v1/empleadores/999999');

        $respuesta->assertStatus(404);
    }

    public function test_sin_autenticar_no_puede_eliminar(): void
    {
        $this->actingAsAdmin();
        $empleador = $this->crearEmpleador();
        $this->salirDeSesion();

        $respuesta = $this->deleteJson("/api/v1/empleadores/{$empleador->id}");

        $respuesta->assertStatus(401);
    }
}
