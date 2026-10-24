<?php

namespace Tests\Feature\Usuarios;

use App\Usuarios\Models\Rol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\TestCase;

class AislamientoEntreEmpresasTest extends TestCase
{
    use ActsAsAdmin;
    use RefreshDatabase;

    public function test_el_listado_de_usuarios_no_incluye_usuarios_de_otra_empresa(): void
    {
        $this->actingAsAdmin();

        $adminEmpresaB = $this->actingAsAdmin();

        $respuesta = $this->getJson('/api/v1/usuarios');

        $respuesta->assertStatus(200);
        $ids = collect($respuesta->json('data'))->pluck('id')->all();
        $this->assertSame([$adminEmpresaB->id], $ids);
    }

    public function test_no_puede_editar_un_usuario_de_otra_empresa(): void
    {
        $usuarioAjeno = $this->actingAsAdmin();

        $this->actingAsAdmin();

        $respuesta = $this->patchJson("/api/v1/usuarios/{$usuarioAjeno->id}", [
            'nombre' => 'Intento ajeno',
            'usuario' => $usuarioAjeno->usuario,
            'rol_id' => $usuarioAjeno->rol_id,
            'activo' => true,
        ]);

        $respuesta->assertStatus(404);
    }

    public function test_no_puede_asignar_un_rol_de_otra_empresa_al_crear_un_usuario(): void
    {
        $this->actingAsAdmin();
        $rolAjeno = Rol::query()->where('nombre', 'admin')->firstOrFail();

        $this->actingAsAdmin();

        $respuesta = $this->postJson('/api/v1/usuarios', [
            'nombre' => 'Intento con rol ajeno',
            'usuario' => 'intento_rol_ajeno',
            'clave' => 'Clave123!',
            'rol_id' => $rolAjeno->id,
            'activo' => true,
        ]);

        $respuesta->assertStatus(422);
        $respuesta->assertJsonValidationErrors('rol_id');
    }
}
