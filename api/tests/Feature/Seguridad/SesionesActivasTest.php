<?php

namespace Tests\Feature\Seguridad;

use App\Auditoria\Enums\AccionAuditoria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\AfirmaAuditoria;
use Tests\TestCase;

class SesionesActivasTest extends TestCase
{
    use ActsAsAdmin;
    use AfirmaAuditoria;
    use RefreshDatabase;

    public function test_sin_autenticar_responde_401(): void
    {
        $this->getJson('/api/v1/seguridad/sesiones')->assertUnauthorized();
        $this->deleteJson('/api/v1/seguridad/sesiones/cualquiera')->assertUnauthorized();
        $this->deleteJson('/api/v1/seguridad/sesiones/empresa/1')->assertUnauthorized();
    }

    public function test_el_administrador_de_una_empresa_no_ve_las_sesiones(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/v1/seguridad/sesiones')->assertForbidden();
    }

    public function test_lista_las_sesiones_con_su_empresa(): void
    {
        $empresa = $this->crearEmpresaDePrueba();
        $usuario = $this->actingAsAdmin($empresa);
        $this->sesion('sesion-admin', $usuario->id);

        $this->actingAsSuperAdmin();

        $respuesta = $this->getJson('/api/v1/seguridad/sesiones')->assertOk();

        $respuesta->assertJsonPath('sesiones.0.id', 'sesion-admin')
            ->assertJsonPath('sesiones.0.usuario_login', $usuario->usuario)
            ->assertJsonPath('sesiones.0.empresa_id', $empresa->id)
            ->assertJsonPath('sesiones.0.nombre_empresa', $empresa->nombre_empresa)
            ->assertJsonPath('sesiones.0.es_superadmin', false);
    }

    public function test_cerrar_una_sesion_la_borra_y_queda_en_la_bitacora(): void
    {
        $empresa = $this->crearEmpresaDePrueba();
        $usuario = $this->actingAsAdmin($empresa);
        $this->sesion('sesion-a-cerrar', $usuario->id);

        $this->actingAsSuperAdmin();

        $this->deleteJson('/api/v1/seguridad/sesiones/sesion-a-cerrar')->assertOk();

        $this->assertDatabaseMissing('sessions', ['id' => 'sesion-a-cerrar']);
        $eventos = $this->eventosDeAuditoria(AccionAuditoria::Logout->value, 'seguridad');
        $this->assertCount(1, $eventos);
        $this->assertNull($eventos[0]['empresa_id']);
    }

    public function test_cerrar_una_sesion_inexistente_responde_404(): void
    {
        $this->actingAsSuperAdmin();

        $this->deleteJson('/api/v1/seguridad/sesiones/no-existe')->assertNotFound();
    }

    public function test_cerrar_las_sesiones_de_una_empresa_las_borra_todas(): void
    {
        $empresa = $this->crearEmpresaDePrueba();
        $usuarioUno = $this->actingAsAdmin($empresa);
        $usuarioDos = $this->actingAsAsesor($empresa);
        $this->sesion('sesion-uno', $usuarioUno->id);
        $this->sesion('sesion-dos', $usuarioDos->id);

        $otraEmpresa = $this->crearEmpresaDePrueba();
        $otroUsuario = $this->actingAsAdmin($otraEmpresa);
        $this->sesion('sesion-otra-empresa', $otroUsuario->id);

        $this->actingAsSuperAdmin();

        $this->deleteJson("/api/v1/seguridad/sesiones/empresa/{$empresa->id}")->assertOk();

        $this->assertDatabaseMissing('sessions', ['id' => 'sesion-uno']);
        $this->assertDatabaseMissing('sessions', ['id' => 'sesion-dos']);
        $this->assertDatabaseHas('sessions', ['id' => 'sesion-otra-empresa']);
    }

    private function sesion(string $id, int $userId): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $userId,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => '',
            'last_activity' => now()->getTimestamp(),
        ]);
    }
}
