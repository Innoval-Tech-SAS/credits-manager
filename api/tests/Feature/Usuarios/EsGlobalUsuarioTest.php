<?php

namespace Tests\Feature\Usuarios;

use App\Usuarios\Models\Rol;
use App\Usuarios\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\TestCase;

class EsGlobalUsuarioTest extends TestCase
{
    use ActsAsAdmin;
    use RefreshDatabase;

    private function payload(int $rolId, array $extra = []): array
    {
        return array_merge([
            'nombre' => 'Asesor de prueba',
            'usuario' => 'asesor_global_'.uniqid(),
            'clave' => 'Clave123!',
            'rol_id' => $rolId,
            'activo' => true,
        ], $extra);
    }

    public function test_un_usuario_nuevo_no_es_global_por_defecto(): void
    {
        $this->actingAsAdmin();
        $rol = Rol::create(['nombre' => 'asesor']);

        $id = $this->postJson('/api/v1/usuarios', $this->payload($rol->id))
            ->assertCreated()->assertJsonPath('usuario.es_global', false)->json('usuario.id');

        $this->assertDatabaseHas('usuarios', ['id' => $id, 'es_global' => false]);
    }

    public function test_se_crea_un_usuario_global_y_se_puede_cambiar_despues(): void
    {
        $this->actingAsAdmin();
        $rol = Rol::create(['nombre' => 'asesor']);

        $id = $this->postJson('/api/v1/usuarios', $this->payload($rol->id, ['es_global' => true]))
            ->assertCreated()->assertJsonPath('usuario.es_global', true)->json('usuario.id');
        $usuario = Usuario::findOrFail($id);

        $this->patchJson("/api/v1/usuarios/{$id}", [
            'nombre' => $usuario->nombre, 'usuario' => $usuario->usuario, 'rol_id' => $rol->id, 'activo' => true, 'es_global' => false,
        ])->assertOk()->assertJsonPath('usuario.es_global', false);

        $this->assertDatabaseHas('usuarios', ['id' => $id, 'es_global' => false]);
    }

    public function test_editar_sin_enviar_es_global_no_lo_cambia(): void
    {
        $this->actingAsAdmin();
        $rol = Rol::create(['nombre' => 'asesor']);
        $usuario = Usuario::create([
            'nombre' => 'Global', 'usuario' => 'global_'.uniqid(), 'password_hash' => 'irrelevante-en-tests',
            'rol_id' => $rol->id, 'activo' => true, 'es_global' => true,
        ]);

        $this->patchJson("/api/v1/usuarios/{$usuario->id}", [
            'nombre' => 'Global editado', 'usuario' => $usuario->usuario, 'rol_id' => $rol->id, 'activo' => true,
        ])->assertOk();

        $this->assertDatabaseHas('usuarios', ['id' => $usuario->id, 'es_global' => true]);
    }

    public function test_es_global_debe_ser_booleano(): void
    {
        $this->actingAsAdmin();
        $rol = Rol::create(['nombre' => 'asesor']);

        $this->postJson('/api/v1/usuarios', $this->payload($rol->id, ['es_global' => 'quizas']))
            ->assertStatus(422)->assertJsonValidationErrors('es_global');
    }
}
