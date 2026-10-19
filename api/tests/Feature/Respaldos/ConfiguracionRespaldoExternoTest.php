<?php

namespace Tests\Feature\Respaldos;

use App\Respaldos\Models\ConfiguracionRespaldoExterno;
use Database\Seeders\ConfiguracionRespaldoExternoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\AfirmaAuditoria;
use Tests\Concerns\SimulaRespaldoExterno;
use Tests\TestCase;

class ConfiguracionRespaldoExternoTest extends TestCase
{
    use ActsAsAdmin;
    use AfirmaAuditoria;
    use RefreshDatabase;
    use SimulaRespaldoExterno;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ConfiguracionRespaldoExternoSeeder::class);
    }

    private function datosValidos(array $sobrescribir = []): array
    {
        return array_merge([
            'activa' => true,
            'bucket' => 'bucket-de-prueba',
            'region' => 'us-east-1',
            'endpoint' => 'https://s3.us-east-1.example.com',
            'uso_path_style' => true,
            'clave_acceso' => 'clave-de-prueba',
            'clave_secreta' => 'secreto-de-prueba',
        ], $sobrescribir);
    }

    public function test_sin_autenticar_responde_401(): void
    {
        $this->getJson('/api/v1/respaldos/externo')->assertUnauthorized();
        $this->patchJson('/api/v1/respaldos/externo', $this->datosValidos())->assertUnauthorized();
        $this->postJson('/api/v1/respaldos/externo/prueba')->assertUnauthorized();
    }

    public function test_el_administrador_de_una_empresa_no_accede(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/v1/respaldos/externo')->assertForbidden();
        $this->patchJson('/api/v1/respaldos/externo', $this->datosValidos())->assertForbidden();
        $this->postJson('/api/v1/respaldos/externo/prueba')->assertForbidden();
    }

    public function test_muestra_sin_configurar_antes_de_guardar_nada(): void
    {
        $this->actingAsSuperAdmin();

        $this->getJson('/api/v1/respaldos/externo')
            ->assertOk()
            ->assertJsonPath('configuracion.activa', false)
            ->assertJsonPath('configuracion.clave_secreta_estado', 'sin_configurar');
    }

    public function test_el_superadmin_guarda_la_configuracion_y_la_clave_secreta_queda_cifrada(): void
    {
        $this->actingAsSuperAdmin();

        $respuesta = $this->patchJson('/api/v1/respaldos/externo', $this->datosValidos());

        $respuesta->assertOk()
            ->assertJsonPath('configuracion.activa', true)
            ->assertJsonPath('configuracion.bucket', 'bucket-de-prueba')
            ->assertJsonPath('configuracion.clave_secreta_estado', 'configurada')
            ->assertDontSee('secreto-de-prueba');

        $crudo = ConfiguracionRespaldoExterno::query()->first()->getRawOriginal('clave_secreta');
        $this->assertNotSame('secreto-de-prueba', $crudo);
        $this->assertSame('secreto-de-prueba', ConfiguracionRespaldoExterno::query()->first()->clave_secreta);
    }

    public function test_guardar_sin_clave_secreta_conserva_la_guardada(): void
    {
        $this->actingAsSuperAdmin();

        $this->patchJson('/api/v1/respaldos/externo', $this->datosValidos())->assertOk();
        $this->patchJson('/api/v1/respaldos/externo', $this->datosValidos(['clave_secreta' => null, 'bucket' => 'otro-bucket']))->assertOk();

        $configuracion = ConfiguracionRespaldoExterno::query()->first();
        $this->assertSame('secreto-de-prueba', $configuracion->clave_secreta);
        $this->assertSame('otro-bucket', $configuracion->bucket);
    }

    public function test_422_al_activar_sin_bucket_ni_credenciales(): void
    {
        $this->actingAsSuperAdmin();

        $this->patchJson('/api/v1/respaldos/externo', $this->datosValidos(['bucket' => null, 'clave_acceso' => null, 'clave_secreta' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['activa']);
    }

    public function test_422_al_activar_sin_clave_secreta_y_sin_una_guardada_antes(): void
    {
        $this->actingAsSuperAdmin();

        $this->patchJson('/api/v1/respaldos/externo', $this->datosValidos(['clave_secreta' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['clave_secreta']);
    }

    public function test_desactivar_no_exige_bucket_ni_credenciales(): void
    {
        $this->actingAsSuperAdmin();

        $this->patchJson('/api/v1/respaldos/externo', [
            'activa' => false,
            'uso_path_style' => false,
        ])->assertOk()->assertJsonPath('configuracion.activa', false);
    }

    public function test_guardar_registra_la_bitacora_sin_la_clave_secreta(): void
    {
        $this->actingAsSuperAdmin();

        $this->patchJson('/api/v1/respaldos/externo', $this->datosValidos())->assertOk();

        $eventos = $this->eventosDeAuditoria('modificar', 'respaldos');
        $this->assertCount(1, $eventos);
        $this->assertStringNotContainsString('secreto-de-prueba', json_encode($eventos));
    }

    public function test_prueba_devuelve_exito_cuando_el_disco_responde(): void
    {
        $this->simulaRespaldoExterno();
        $this->actingAsSuperAdmin();
        $this->patchJson('/api/v1/respaldos/externo', $this->datosValidos())->assertOk();

        $this->postJson('/api/v1/respaldos/externo/prueba')
            ->assertOk()
            ->assertJsonPath('exitoso', true)
            ->assertDontSee('secreto-de-prueba');
    }

    public function test_prueba_sin_credenciales_responde_sin_exito_y_sin_intentar_conectar(): void
    {
        $this->actingAsSuperAdmin();

        $this->postJson('/api/v1/respaldos/externo/prueba')
            ->assertOk()
            ->assertJsonPath('exitoso', false);
    }
}
