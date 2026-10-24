<?php

namespace Tests\Feature\Seguridad;

use App\Auditoria\Models\BitacoraAuditoria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\ActsAsAdmin;
use Tests\TestCase;

class AccesosTest extends TestCase
{
    use ActsAsAdmin;
    use RefreshDatabase;

    public function test_sin_autenticar_responde_401(): void
    {
        $this->getJson('/api/v1/seguridad/accesos')->assertUnauthorized();
        $this->getJson('/api/v1/seguridad/accesos/alerta')->assertUnauthorized();
    }

    public function test_el_administrador_de_una_empresa_no_ve_los_accesos(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/v1/seguridad/accesos')->assertForbidden();
        $this->getJson('/api/v1/seguridad/accesos/alerta')->assertForbidden();
    }

    public function test_solo_trae_eventos_de_acceso_filtrando_los_demas(): void
    {
        $empresa = $this->crearEmpresaDePrueba();
        $this->evento('login', $empresa->id);
        $this->evento('login_fallido', $empresa->id);
        $this->evento('logout', $empresa->id);
        $this->evento('crear', $empresa->id); // no es un acceso: no debe aparecer

        $this->actingAsSuperAdmin();

        $respuesta = $this->getJson('/api/v1/seguridad/accesos')->assertOk();

        $this->assertCount(3, $respuesta->json('data'));
    }

    public function test_filtra_por_empresa(): void
    {
        $empresaUno = $this->crearEmpresaDePrueba();
        $empresaDos = $this->crearEmpresaDePrueba();
        $this->evento('login', $empresaUno->id);
        $this->evento('login', $empresaDos->id);

        $this->actingAsSuperAdmin();

        $respuesta = $this->getJson("/api/v1/seguridad/accesos?empresa_id={$empresaUno->id}")->assertOk();

        $this->assertCount(1, $respuesta->json('data'));
        $this->assertSame($empresaUno->id, $respuesta->json('data.0.empresa_id'));
    }

    public function test_alerta_de_fuerza_bruta_se_activa_con_varios_intentos_desde_la_misma_ip(): void
    {
        $this->actingAsSuperAdmin();

        $this->getJson('/api/v1/seguridad/accesos/alerta')->assertOk()->assertJsonPath('alerta_fuerza_bruta', false);

        for ($i = 0; $i < 5; $i++) {
            $this->evento('login_fallido', null, '203.0.113.9');
        }

        $this->getJson('/api/v1/seguridad/accesos/alerta')->assertOk()->assertJsonPath('alerta_fuerza_bruta', true);
    }

    private function evento(string $accion, ?int $empresaId, string $ip = '127.0.0.1'): void
    {
        BitacoraAuditoria::query()->create([
            'evento_id' => (string) Str::uuid(),
            'empresa_id' => $empresaId,
            'modulo' => 'auth',
            'accion' => $accion,
            'descripcion' => 'Evento de prueba.',
            'ip' => $ip,
            'fecha_hora' => now(),
            'creado_en' => now(),
        ]);
    }
}
