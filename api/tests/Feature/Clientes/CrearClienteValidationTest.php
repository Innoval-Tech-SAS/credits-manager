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
            'nombres' => 'Juan',
            'apellidos' => 'Pérez',
            'tipo_documento' => TipoDocumentoTercero::Cc->value,
            'numero_documento' => '1000000001',
            'direccion' => 'Calle 10 # 20-30',
            'barrio' => 'Centro',
            'ciudad' => 'Bogotá',
            'celular' => '3001234567',
            'telefono_fijo' => '6011234567',
            'correo' => 'juan@example.com',
            'empleador_id' => $empleadorId ?? $this->crearEmpleador()->id,
            'asesor_id' => auth()->id(),
        ];
    }

    public function test_crea_cliente_con_datos_validos(): void
    {
        $this->actingAsAdmin();

        $respuesta = $this->postJson('/api/v1/clientes', $this->payloadValido());

        $respuesta->assertStatus(201);
        $this->assertDatabaseHas('terceros', [
            'numero_documento' => '1000000001',
            'tipo_persona' => 'natural',
            'nombres' => 'Juan',
            'apellidos' => 'Pérez',
            'nombre' => 'Juan Pérez',
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

    public function test_rechaza_un_nit_porque_los_clientes_son_personas_naturales(): void
    {
        $this->actingAsAdmin();

        $respuesta = $this->postJson('/api/v1/clientes', [...$this->payloadValido(), 'tipo_documento' => TipoDocumentoTercero::Nit->value]);

        $respuesta->assertStatus(422)->assertJsonValidationErrors('tipo_documento');
        $this->assertDatabaseCount('clientes', 0);
    }

    public function test_rechaza_nombres_o_apellidos_faltantes(): void
    {
        $this->actingAsAdmin();

        foreach (['nombres', 'apellidos'] as $campo) {
            $payload = $this->payloadValido();
            unset($payload[$campo]);

            $this->postJson('/api/v1/clientes', $payload)->assertStatus(422)->assertJsonValidationErrors($campo);
        }
    }

    public function test_el_cliente_siempre_queda_como_persona_natural_aunque_el_payload_diga_otra_cosa(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/clientes', [...$this->payloadValido(), 'tipo_persona' => 'juridica', 'razon_social' => 'Intruso SAS'])->assertStatus(201);

        $this->assertDatabaseHas('terceros', ['numero_documento' => '1000000001', 'tipo_persona' => 'natural', 'razon_social' => null]);
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
