<?php

namespace Tests\Feature\Empresas;

use App\Auditoria\Constants\AuditoriaConstants;
use App\Empresas\Models\Empresa;
use Database\Seeders\PermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\TestCase;

class EmpresaAuditoriaTest extends TestCase
{
    use ActsAsAdmin;
    use RefreshDatabase;

    private function datosEmpresa(): array
    {
        return [
            'nombre_empresa' => 'Empresa auditada SAS', 'nit' => '900555444-1', 'direccion' => 'Calle 1', 'municipio_id' => $this->municipioDePrueba()->id, 'telefono' => '3001112222',
            'correo' => 'auditada@example.com', 'admin_nombre' => 'Admin Auditado', 'admin_usuario' => 'admin_auditado', 'admin_password' => 'Clave123!',
        ];
    }

    private function datosEdicion(object $empresa, array $cambios = []): array
    {
        return array_merge([
            'nombre_empresa' => $empresa->nombre_empresa, 'nit' => $empresa->nit, 'direccion' => $empresa->direccion,
            'municipio_id' => $empresa->municipio_id, 'telefono' => $empresa->telefono, 'correo' => $empresa->correo,
        ], $cambios);
    }

    public function test_el_superadmin_que_crea_una_empresa_deja_un_evento_sin_empresa_que_apunta_a_la_creada(): void
    {
        $this->seed(PermisoSeeder::class);
        $superadmin = $this->actingAsSuperAdmin();

        $empresaId = $this->postJson('/api/v1/empresas', $this->datosEmpresa())->assertCreated()->json('empresa.id');

        $payloads = $this->redis->payloads(AuditoriaConstants::STREAM);

        $this->assertCount(1, $payloads);
        $this->assertSame('crear', $payloads[0]['accion']);
        $this->assertSame('empresas', $payloads[0]['modulo']);
        $this->assertNull($payloads[0]['empresa_id']);
        $this->assertSame('empresa', $payloads[0]['entidad']);
        $this->assertSame($empresaId, $payloads[0]['entidad_id']);
        $this->assertSame($superadmin->id, $payloads[0]['autor']['usuario_id']);
        $this->assertSame('admin_auditado', $payloads[0]['valores_nuevos']['admin_usuario']);
        $this->assertArrayNotHasKey('admin_password', $payloads[0]['valores_nuevos']);
    }

    public function test_una_creacion_que_falla_no_deja_evento(): void
    {
        $this->seed(PermisoSeeder::class);
        $this->actingAsSuperAdmin();

        $this->postJson('/api/v1/empresas', [])->assertStatus(422);

        $this->assertSame([], $this->redis->payloads(AuditoriaConstants::STREAM));
    }

    public function test_editar_una_empresa_registra_solo_lo_que_cambio(): void
    {
        $superadmin = $this->actingAsSuperAdmin();
        $empresa = $this->crearEmpresaDePrueba(['telefono' => '3000000000']);

        $this->patchJson("/api/v1/empresas/{$empresa->id}", $this->datosEdicion($empresa, ['telefono' => '3119998888']))->assertOk();

        $payloads = $this->redis->payloads(AuditoriaConstants::STREAM);

        $this->assertCount(1, $payloads);
        $this->assertSame('modificar', $payloads[0]['accion']);
        $this->assertSame($empresa->id, $payloads[0]['entidad_id']);
        $this->assertSame(['telefono' => '3000000000'], $payloads[0]['valores_anteriores']);
        $this->assertSame(['telefono' => '3119998888'], $payloads[0]['valores_nuevos']);
        $this->assertSame($superadmin->id, $payloads[0]['autor']['usuario_id']);
    }

    public function test_editar_sin_cambios_no_genera_evento(): void
    {
        $this->actingAsSuperAdmin();
        $empresa = $this->crearEmpresaDePrueba();

        $this->patchJson("/api/v1/empresas/{$empresa->id}", $this->datosEdicion($empresa))->assertOk();

        $this->assertSame([], $this->redis->payloads(AuditoriaConstants::STREAM));
    }

    public function test_el_admin_que_edita_su_propia_empresa_deja_el_evento_en_su_empresa(): void
    {
        $admin = $this->actingAsAdmin();
        $empresa = Empresa::find($admin->empresa_id);

        $this->patchJson('/api/v1/empresa', $this->datosEdicion($empresa, ['direccion' => 'Carrera 50 # 10-20']))->assertOk();

        $payload = $this->redis->payloads(AuditoriaConstants::STREAM)[0];

        $this->assertSame($empresa->id, $payload['empresa_id']);
        $this->assertSame(['direccion' => 'Carrera 50 # 10-20'], $payload['valores_nuevos']);
    }
}
