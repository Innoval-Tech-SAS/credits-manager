<?php

namespace Tests\Feature\Usuarios;

use App\Usuarios\Constants\UsuarioConstants;
use App\Usuarios\Models\Rol;
use App\Usuarios\Models\Usuario;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\TestCase;

class EsAsesorGlobalUsuarioTest extends TestCase
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

    public function test_un_usuario_nuevo_no_es_asesor_global_por_defecto(): void
    {
        $this->actingAsAdmin();
        $rol = Rol::create(['nombre' => UsuarioConstants::ROL_ASESOR]);

        $id = $this->postJson('/api/v1/usuarios', $this->payload($rol->id))
            ->assertCreated()->assertJsonPath('usuario.es_asesor_global', false)->json('usuario.id');

        $this->assertDatabaseHas('usuarios', ['id' => $id, 'es_asesor_global' => false]);
    }

    public function test_se_crea_un_asesor_global_y_se_puede_cambiar_despues(): void
    {
        $this->actingAsAdmin();
        $rol = Rol::create(['nombre' => UsuarioConstants::ROL_ASESOR]);

        $id = $this->postJson('/api/v1/usuarios', $this->payload($rol->id, ['es_asesor_global' => true]))
            ->assertCreated()->assertJsonPath('usuario.es_asesor_global', true)->json('usuario.id');
        $usuario = Usuario::findOrFail($id);

        $this->patchJson("/api/v1/usuarios/{$id}", [
            'nombre' => $usuario->nombre, 'usuario' => $usuario->usuario, 'rol_id' => $rol->id, 'activo' => true, 'es_asesor_global' => false,
        ])->assertOk()->assertJsonPath('usuario.es_asesor_global', false);

        $this->assertDatabaseHas('usuarios', ['id' => $id, 'es_asesor_global' => false]);
    }

    public function test_editar_sin_enviar_es_asesor_global_no_lo_cambia(): void
    {
        $this->actingAsAdmin();
        $rol = Rol::create(['nombre' => UsuarioConstants::ROL_ASESOR]);
        $usuario = Usuario::create([
            'nombre' => 'Global', 'usuario' => 'global_'.uniqid(), 'password_hash' => 'irrelevante-en-tests',
            'rol_id' => $rol->id, 'activo' => true, 'es_asesor_global' => true,
        ]);

        $this->patchJson("/api/v1/usuarios/{$usuario->id}", [
            'nombre' => 'Global editado', 'usuario' => $usuario->usuario, 'rol_id' => $rol->id, 'activo' => true,
        ])->assertOk();

        $this->assertDatabaseHas('usuarios', ['id' => $usuario->id, 'es_asesor_global' => true]);
    }

    public function test_es_asesor_global_debe_ser_booleano(): void
    {
        $this->actingAsAdmin();
        $rol = Rol::create(['nombre' => UsuarioConstants::ROL_ASESOR]);

        $this->postJson('/api/v1/usuarios', $this->payload($rol->id, ['es_asesor_global' => 'quizas']))
            ->assertStatus(422)->assertJsonValidationErrors('es_asesor_global');
    }

    public function test_rechaza_alcance_global_para_un_rol_que_no_es_asesor(): void
    {
        $this->actingAsAdmin();
        $rol = Rol::create(['nombre' => 'contable_test']);

        $this->postJson('/api/v1/usuarios', $this->payload($rol->id, ['es_asesor_global' => true]))
            ->assertStatus(422)->assertJsonValidationErrors('es_asesor_global');

        $this->assertDatabaseMissing('usuarios', ['rol_id' => $rol->id]);
    }

    public function test_acepta_alcance_global_en_falso_para_un_rol_que_no_es_asesor(): void
    {
        $this->actingAsAdmin();
        $rol = Rol::create(['nombre' => 'contable_test']);

        $this->postJson('/api/v1/usuarios', $this->payload($rol->id, ['es_asesor_global' => false]))->assertCreated();
    }

    public function test_un_asesor_global_que_cambia_de_rol_pierde_el_alcance(): void
    {
        $this->actingAsAdmin();
        $asesor = Rol::create(['nombre' => UsuarioConstants::ROL_ASESOR]);
        $contable = Rol::create(['nombre' => 'contable_test']);
        $usuario = Usuario::create([
            'nombre' => 'Global', 'usuario' => 'global_'.uniqid(), 'password_hash' => 'irrelevante-en-tests',
            'rol_id' => $asesor->id, 'activo' => true, 'es_asesor_global' => true,
        ]);

        $this->patchJson("/api/v1/usuarios/{$usuario->id}", [
            'nombre' => $usuario->nombre, 'usuario' => $usuario->usuario, 'rol_id' => $contable->id, 'activo' => true,
        ])->assertOk()->assertJsonPath('usuario.es_asesor_global', false);

        $this->assertDatabaseHas('usuarios', ['id' => $usuario->id, 'rol_id' => $contable->id, 'es_asesor_global' => false]);
    }

    public function test_la_base_rechaza_alcance_global_fuera_del_rol_asesor(): void
    {
        $admin = $this->actingAsAdmin();

        try {
            $admin->update(['es_asesor_global' => true]);
            $this->fail('El UPDATE debió ser rechazado por el trigger.');
        } catch (QueryException $excepcion) {
            $this->assertStringContainsString('Solo un asesor puede tener alcance global', $excepcion->getMessage());
        }
    }
}
