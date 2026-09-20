<?php

namespace Tests\Feature\Auth;

use App\Auditoria\Constants\AuditoriaConstants;
use App\Usuarios\Models\Rol;
use App\Usuarios\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\AfirmaAuditoria;
use Tests\TestCase;

class AuthAuditoriaTest extends TestCase
{
    use ActsAsAdmin;
    use AfirmaAuditoria;
    use RefreshDatabase;

    private function referer(): string
    {
        return 'https://'.collect(config('sanctum.stateful'))->first();
    }

    private function crearAdminConClave(string $clave): Usuario
    {
        $empresa = $this->crearEmpresaDePrueba();
        $rol = Rol::create(['empresa_id' => $empresa->id, 'nombre' => 'admin']);

        return Usuario::create([
            'empresa_id' => $empresa->id,
            'nombre' => 'Admin de prueba',
            'usuario' => 'audit_login_'.uniqid(),
            'password_hash' => $clave,
            'rol_id' => $rol->id,
            'activo' => true,
        ]);
    }

    public function test_el_login_emite_un_evento_con_autor_empresa_e_ip(): void
    {
        $usuario = $this->crearAdminConClave('Clave123!');

        $this->withHeader('Referer', $this->referer())
            ->postJson('/api/v1/login', ['usuario' => $usuario->usuario, 'clave' => 'Clave123!'])
            ->assertOk();

        $payloads = $this->redis->payloads(AuditoriaConstants::STREAM);

        $this->assertCount(1, $payloads);
        $this->assertSame('login', $payloads[0]['accion']);
        $this->assertSame('auth', $payloads[0]['modulo']);
        $this->assertSame($usuario->empresa_id, $payloads[0]['empresa_id']);
        $this->assertSame($usuario->id, $payloads[0]['autor']['usuario_id']);
        $this->assertSame('admin', $payloads[0]['autor']['rol']);
        $this->assertSame('127.0.0.1', $payloads[0]['ip']);
    }

    private function intentarLogin(string $usuario, string $clave): TestResponse
    {
        return $this->withHeader('Referer', $this->referer())
            ->postJson('/api/v1/login', ['usuario' => $usuario, 'clave' => $clave]);
    }

    public function test_un_login_con_clave_incorrecta_registra_el_intento_con_su_razon(): void
    {
        $usuario = $this->crearAdminConClave('Clave123!');

        $this->intentarLogin($usuario->usuario, 'ClaveErrada#99')->assertStatus(422)->assertJsonValidationErrors(['usuario']);

        $eventos = $this->eventosDeAuditoria('login_fallido', 'auth');

        $this->assertCount(1, $eventos);
        $this->assertSame('Contraseña incorrecta', $eventos[0]['motivo']);
        $this->assertSame($usuario->empresa_id, $eventos[0]['empresa_id']);
        $this->assertSame($usuario->id, $eventos[0]['autor']['usuario_id']);
        $this->assertSame($usuario->usuario, $eventos[0]['autor']['usuario']);
        $this->assertSame('usuario', $eventos[0]['entidad']);
        $this->assertSame($usuario->id, $eventos[0]['entidad_id']);
        $this->assertSame('127.0.0.1', $eventos[0]['ip']);
        $this->assertStringNotContainsString('ClaveErrada#99', json_encode($eventos[0]));
    }

    public function test_un_login_con_un_usuario_inexistente_queda_sin_empresa_y_con_el_nombre_digitado(): void
    {
        $this->intentarLogin('Fantasma.Nadie', 'Clave123!')->assertStatus(422);

        $eventos = $this->eventosDeAuditoria('login_fallido', 'auth');

        $this->assertCount(1, $eventos);
        $this->assertSame('Usuario inexistente', $eventos[0]['motivo']);
        $this->assertNull($eventos[0]['empresa_id']);
        $this->assertNull($eventos[0]['autor']['usuario_id']);
        $this->assertSame('fantasma.nadie', $eventos[0]['autor']['usuario']);
        $this->assertNull($eventos[0]['entidad']);
    }

    public function test_un_login_de_un_usuario_inactivo_con_la_clave_correcta_registra_usuario_inactivo(): void
    {
        $usuario = $this->crearAdminConClave('Clave123!');
        $usuario->update(['activo' => false]);

        $this->intentarLogin($usuario->usuario, 'Clave123!')->assertStatus(422);

        $eventos = $this->eventosDeAuditoria('login_fallido', 'auth');

        $this->assertCount(1, $eventos);
        $this->assertSame('Usuario inactivo', $eventos[0]['motivo']);
        $this->assertSame($usuario->empresa_id, $eventos[0]['empresa_id']);
    }

    public function test_un_login_de_un_usuario_inactivo_con_la_clave_incorrecta_registra_clave_incorrecta(): void
    {
        $usuario = $this->crearAdminConClave('Clave123!');
        $usuario->update(['activo' => false]);

        $this->intentarLogin($usuario->usuario, 'ClaveErrada#99')->assertStatus(422);

        $this->assertSame('Contraseña incorrecta', $this->eventosDeAuditoria('login_fallido')[0]['motivo']);
    }

    public function test_un_login_de_un_usuario_dado_de_baja_registra_usuario_dado_de_baja(): void
    {
        $usuario = $this->crearAdminConClave('Clave123!');
        $usuario->delete();

        $this->intentarLogin($usuario->usuario, 'Clave123!')->assertStatus(422);

        $eventos = $this->eventosDeAuditoria('login_fallido', 'auth');

        $this->assertCount(1, $eventos);
        $this->assertSame('Usuario dado de baja', $eventos[0]['motivo']);
        $this->assertSame($usuario->empresa_id, $eventos[0]['empresa_id']);
    }

    public function test_el_fallo_del_superadmin_no_pertenece_a_ninguna_empresa(): void
    {
        Usuario::create([
            'nombre' => 'Super', 'usuario' => 'super_fallo_test', 'password_hash' => 'Clave123!',
            'activo' => true, 'es_superadmin' => true,
        ]);

        $this->intentarLogin('super_fallo_test', 'ClaveErrada#99')->assertStatus(422);

        $evento = $this->eventosDeAuditoria('login_fallido')[0];

        $this->assertNull($evento['empresa_id']);
        $this->assertTrue($evento['autor']['es_superadmin']);
    }

    public function test_el_mensaje_al_cliente_no_revela_si_el_usuario_existe(): void
    {
        $usuario = $this->crearAdminConClave('Clave123!');

        $existente = $this->intentarLogin($usuario->usuario, 'ClaveErrada#99');
        $inexistente = $this->intentarLogin('fantasma.nadie', 'ClaveErrada#99');

        $this->assertSame($existente->json(), $inexistente->json());
    }

    public function test_el_nombre_digitado_se_recorta_para_no_llenar_la_bitacora(): void
    {
        $this->intentarLogin(str_repeat('a', 500), 'Clave123!')->assertStatus(422);

        $this->assertSame(60, mb_strlen($this->eventosDeAuditoria('login_fallido')[0]['autor']['usuario']));
    }

    public function test_un_mismo_ip_no_puede_llenar_la_bitacora_probando_usuarios_distintos(): void
    {
        for ($intento = 1; $intento <= 30; $intento++) {
            $this->intentarLogin("inexistente_{$intento}", 'Clave123!')->assertStatus(422);
        }

        $this->intentarLogin('inexistente_31', 'Clave123!')->assertStatus(429);

        $this->assertCount(30, $this->eventosDeAuditoria('login_fallido'));
    }

    public function test_el_logout_emite_un_evento_antes_de_cerrar_la_sesion(): void
    {
        $admin = $this->actingAsAdmin();

        $this->withHeader('Referer', $this->referer())->postJson('/api/v1/logout')->assertNoContent();

        $payloads = $this->redis->payloads(AuditoriaConstants::STREAM);

        $this->assertCount(1, $payloads);
        $this->assertSame('logout', $payloads[0]['accion']);
        $this->assertSame($admin->id, $payloads[0]['autor']['usuario_id']);
        $this->assertSame($admin->empresa_id, $payloads[0]['empresa_id']);
    }

    public function test_el_login_del_superadmin_no_pertenece_a_ninguna_empresa(): void
    {
        Usuario::create([
            'nombre' => 'Super', 'usuario' => 'super_login_test', 'password_hash' => 'Clave123!',
            'activo' => true, 'es_superadmin' => true,
        ]);

        $this->withHeader('Referer', $this->referer())
            ->postJson('/api/v1/login', ['usuario' => 'super_login_test', 'clave' => 'Clave123!'])
            ->assertOk();

        $payload = $this->redis->payloads(AuditoriaConstants::STREAM)[0];

        $this->assertNull($payload['empresa_id']);
        $this->assertTrue($payload['autor']['es_superadmin']);
    }

    public function test_si_redis_esta_caido_el_login_igual_funciona(): void
    {
        $usuario = $this->crearAdminConClave('Clave123!');
        $this->redis->caido = true;

        $this->withHeader('Referer', $this->referer())
            ->postJson('/api/v1/login', ['usuario' => $usuario->usuario, 'clave' => 'Clave123!'])
            ->assertOk();
    }
}
