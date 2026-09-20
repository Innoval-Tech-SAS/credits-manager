<?php

namespace Tests\Feature\Clientes;

use App\Terceros\Enums\TipoDocumentoTercero;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesCreditFixtures;
use Tests\TestCase;

class EditarClienteTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesCreditFixtures;
    use RefreshDatabase;

    private function payloadValido(?int $empleadorId = null): array
    {
        return [
            'nombre' => 'Juan Pérez Editado',
            'tipo_documento' => TipoDocumentoTercero::Cc->value,
            'numero_documento' => '1000000001',
            'direccion' => 'Calle 10 # 20-30',
            'ciudad' => 'Bogotá',
            'celular' => '3001234567',
            'correo' => 'juan@example.com',
            'empleador_id' => $empleadorId ?? $this->crearEmpleador()->id,
        ];
    }

    public function test_edita_datos_del_cliente(): void
    {
        $this->actingAsAdmin();
        $cliente = $this->crearCliente();

        $respuesta = $this->patchJson("/api/v1/clientes/{$cliente->id}", $this->payloadValido());

        $respuesta->assertStatus(200);
        $this->assertDatabaseHas('terceros', [
            'id' => $cliente->fresh()->tercero_id,
            'nombre' => 'Juan Pérez Editado',
            'ciudad' => 'Bogotá',
        ]);
    }

    public function test_puede_desactivar_y_reactivar_un_cliente(): void
    {
        $this->actingAsAdmin();
        $cliente = $this->crearCliente();

        $respuesta = $this->patchJson("/api/v1/clientes/{$cliente->id}", [...$this->payloadValido(), 'activo' => false]);

        $respuesta->assertStatus(200);
        $respuesta->assertJsonPath('cliente.activo', false);
        $this->assertDatabaseHas('clientes', ['id' => $cliente->id, 'activo' => false]);

        $respuesta = $this->patchJson("/api/v1/clientes/{$cliente->id}", [...$this->payloadValido(), 'activo' => true]);

        $respuesta->assertStatus(200);
        $this->assertDatabaseHas('clientes', ['id' => $cliente->id, 'activo' => true]);
    }

    public function test_omitir_activo_no_lo_modifica(): void
    {
        $this->actingAsAdmin();
        $cliente = $this->crearCliente(['activo' => false]);

        $respuesta = $this->patchJson("/api/v1/clientes/{$cliente->id}", $this->payloadValido());

        $respuesta->assertStatus(200);
        $this->assertDatabaseHas('clientes', ['id' => $cliente->id, 'activo' => false]);
    }

    public function test_rechaza_numero_documento_de_otro_cliente(): void
    {
        $this->actingAsAdmin();
        $this->crearCliente(['numero_documento' => '9999999999']);
        $cliente = $this->crearCliente();

        $respuesta = $this->patchJson("/api/v1/clientes/{$cliente->id}", [
            ...$this->payloadValido(),
            'numero_documento' => '9999999999',
        ]);

        $respuesta->assertStatus(422)->assertJsonValidationErrors('numero_documento');
    }

    public function test_cliente_inexistente_devuelve_404(): void
    {
        $this->actingAsAdmin();

        $respuesta = $this->patchJson('/api/v1/clientes/999999', $this->payloadValido());

        $respuesta->assertStatus(404);
    }

    public function test_sin_autenticar_no_puede_editar(): void
    {
        $this->actingAsAdmin();
        $cliente = $this->crearCliente();
        $this->salirDeSesion();

        $respuesta = $this->patchJson("/api/v1/clientes/{$cliente->id}", $this->payloadValido(999));

        $respuesta->assertStatus(401);
    }

    public function test_asesor_sin_permiso_no_puede_editar(): void
    {
        $this->actingAsAsesor();
        $cliente = $this->crearCliente();

        $respuesta = $this->patchJson("/api/v1/clientes/{$cliente->id}", $this->payloadValido());

        $respuesta->assertStatus(403);
    }
}
