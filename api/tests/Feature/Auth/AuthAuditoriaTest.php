<?php

namespace Tests\Feature\Auth;

use App\Auditoria\Constants\AuditoriaConstants;
use App\Usuarios\Models\Rol;
use App\Usuarios\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\TestCase;

class AuthAuditoriaTest extends TestCase
{
    use ActsAsAdmin;
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

    public function test_un_login_fallido_no_emite_evento(): void
    {
        $usuario = $this->crearAdminConClave('Clave123!');

        $this->withHeader('Referer', $this->referer())
            ->postJson('/api/v1/login', ['usuario' => $usuario->usuario, 'clave' => 'incorrecta'])
            ->assertStatus(422);

        $this->assertSame([], $this->redis->payloads(AuditoriaConstants::STREAM));
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
