<?php

namespace Tests\Feature\Clientes;

use App\Terceros\Enums\TipoDocumentoTercero;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesCreditFixtures;
use Tests\TestCase;

class CrearClienteValidationTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesCreditFixtures;
    use RefreshDatabase;

    private function payloadValido(?int $empleadorId = null): array
    {
        return [
            'nombre' => 'Juan Pérez',
            'tipo_documento' => TipoDocumentoTercero::Cc->value,
            'numero_documento' => '1000000001',
            'direccion' => 'Calle 10 # 20-30',
            'barrio' => 'Centro',
            'ciudad' => 'Bogotá',
            'celular' => '3001234567',
            'telefono_fijo' => '6011234567',
            'correo' => 'juan@example.com',
            'empleador_id' => $empleadorId ?? $this->crearEmpleador()->id,
        ];
    }

    public function test_crea_cliente_con_datos_validos(): void
    {
        $this->actingAsAdmin();

        $respuesta = $this->postJson('/api/v1/clientes', $this->payloadValido());

        $respuesta->assertStatus(201);
        $this->assertDatabaseHas('terceros', [
            'numero_documento' => '1000000001',
            'direccion' => 'Calle 10 # 20-30',
            'ciudad' => 'Bogotá',
            'celular' => '3001234567',
            'telefono_fijo' => '6011234567',
        ]);
        $this->assertDatabaseHas('clientes', ['activo' => true]);
    }

    public function test_crea_cliente_sin_barrio_ni_telefono_fijo(): void
    {
        $this->actingAsAdmin();

        $payload = $this->payloadValido();
        unset($payload['barrio'], $payload['telefono_fijo']);

        $respuesta = $this->postJson('/api/v1/clientes', $payload);

        $respuesta->assertStatus(201);
    }

    public function test_rechaza_numero_documento_duplicado(): void
    {
        $this->actingAsAdmin();
        $this->crearCliente(['numero_documento' => '1000000001']);

        $respuesta = $this->postJson('/api/v1/clientes', $this->payloadValido());

        $respuesta->assertStatus(422)->assertJsonValidationErrors('numero_documento');
        $this->assertDatabaseCount('clientes', 1);
    }

    public function test_rechaza_direccion_o_ciudad_faltantes(): void
    {
        $this->actingAsAdmin();

        foreach (['direccion', 'ciudad', 'celular'] as $campo) {
            $payload = $this->payloadValido();
            unset($payload[$campo]);

            $respuesta = $this->postJson('/api/v1/clientes', $payload);

            $respuesta->assertStatus(422)->assertJsonValidationErrors($campo);
        }
    }

    public function test_rechaza_empleador_inexistente(): void
    {
        $this->actingAsAdmin();

        $respuesta = $this->postJson('/api/v1/clientes', $this->payloadValido(999999));

        $respuesta->assertStatus(422)->assertJsonValidationErrors('empleador_id');
    }

    public function test_sin_autenticar_no_puede_crear_clientes(): void
    {
        $respuesta = $this->postJson('/api/v1/clientes', $this->payloadValido(999));

        $respuesta->assertStatus(401);
    }

    public function test_asesor_sin_permiso_de_clientes_no_puede_crear(): void
    {
        $this->actingAsAsesor();

        $respuesta = $this->postJson('/api/v1/clientes', $this->payloadValido());

        $respuesta->assertStatus(403);
        $this->assertDatabaseCount('clientes', 0);
    }
}
