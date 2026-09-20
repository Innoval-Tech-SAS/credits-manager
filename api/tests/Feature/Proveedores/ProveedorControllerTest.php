<?php

namespace Tests\Feature\Proveedores;

use App\Proveedores\Models\Proveedor;
use App\Terceros\Enums\TipoDocumentoTercero;
use App\Terceros\Models\Tercero;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\TestCase;

class ProveedorControllerTest extends TestCase
{
    use ActsAsAdmin;
    use RefreshDatabase;

    private function payloadValido(): array
    {
        return [
            'tipo_documento' => TipoDocumentoTercero::Nit->value,
            'numero_documento' => '900123456',
            'nombre' => 'Proveedor de prueba S.A.S.',
            'celular' => '3000000000',
            'correo' => 'proveedor@example.com',
        ];
    }

    public function test_crea_un_proveedor_con_su_tercero(): void
    {
        $this->actingAsAdmin();

        $respuesta = $this->postJson('/api/v1/proveedores', $this->payloadValido());

        $respuesta->assertStatus(201);
        $this->assertDatabaseHas('terceros', ['numero_documento' => '900123456', 'nombre' => 'Proveedor de prueba S.A.S.']);
        $this->assertDatabaseHas('proveedores', ['tercero_id' => $respuesta->json('proveedor.tercero_id'), 'activo' => true]);
    }

    public function test_rechaza_documento_duplicado_en_la_misma_empresa(): void
    {
        $this->actingAsAdmin();
        $this->postJson('/api/v1/proveedores', $this->payloadValido())->assertStatus(201);

        $respuesta = $this->postJson('/api/v1/proveedores', $this->payloadValido());

        $respuesta->assertStatus(422)->assertJsonValidationErrors('numero_documento');
    }

    public function test_edita_un_proveedor(): void
    {
        $this->actingAsAdmin();
        $creado = $this->postJson('/api/v1/proveedores', $this->payloadValido())->json('proveedor');

        $respuesta = $this->patchJson("/api/v1/proveedores/{$creado['id']}", [
            ...$this->payloadValido(),
            'nombre' => 'Proveedor editado S.A.S.',
        ]);

        $respuesta->assertStatus(200);
        $this->assertDatabaseHas('terceros', ['id' => $creado['tercero_id'], 'nombre' => 'Proveedor editado S.A.S.']);
    }

    public function test_rechaza_edicion_sin_nombre(): void
    {
        $this->actingAsAdmin();
        $creado = $this->postJson('/api/v1/proveedores', $this->payloadValido())->json('proveedor');

        $respuesta = $this->patchJson("/api/v1/proveedores/{$creado['id']}", [
            ...$this->payloadValido(),
            'nombre' => '',
        ]);

        $respuesta->assertStatus(422)->assertJsonValidationErrors('nombre');
    }

    public function test_sin_autenticar_no_puede_editar(): void
    {
        $this->actingAsAdmin();
        $tercero = Tercero::create([
            'tipo_documento' => TipoDocumentoTercero::Nit,
            'numero_documento' => '900999999',
            'nombre' => 'Proveedor sin autenticar',
        ]);
        $proveedor = Proveedor::create(['tercero_id' => $tercero->id, 'activo' => true]);
        $this->salirDeSesion();

        $respuesta = $this->patchJson("/api/v1/proveedores/{$proveedor->id}", $this->payloadValido());

        $respuesta->assertStatus(401);
    }

    public function test_asesor_sin_permiso_no_puede_editar(): void
    {
        $usuario = $this->actingAsAsesor();
        $tercero = Tercero::create([
            'empresa_id' => $usuario->empresa_id,
            'tipo_documento' => TipoDocumentoTercero::Nit,
            'numero_documento' => '900999998',
            'nombre' => 'Proveedor de prueba',
        ]);
        $proveedor = Proveedor::create(['empresa_id' => $usuario->empresa_id, 'tercero_id' => $tercero->id, 'activo' => true]);

        $respuesta = $this->patchJson("/api/v1/proveedores/{$proveedor->id}", $this->payloadValido());

        $respuesta->assertStatus(403);
    }

    public function test_proveedor_inexistente_devuelve_404_al_consultar(): void
    {
        $this->actingAsAdmin();

        $respuesta = $this->getJson('/api/v1/proveedores/999999');

        $respuesta->assertStatus(404);
    }

    public function test_proveedor_inexistente_devuelve_404_al_editar(): void
    {
        $this->actingAsAdmin();

        $respuesta = $this->patchJson('/api/v1/proveedores/999999', $this->payloadValido());

        $respuesta->assertStatus(404);
    }

    public function test_lista_proveedores_paginados(): void
    {
        $this->actingAsAdmin();
        $this->postJson('/api/v1/proveedores', $this->payloadValido())->assertStatus(201);
        $this->postJson('/api/v1/proveedores', [
            ...$this->payloadValido(),
            'numero_documento' => '900123457',
        ])->assertStatus(201);

        $respuesta = $this->getJson('/api/v1/proveedores');

        $respuesta->assertStatus(200)->assertJsonCount(2, 'data');
    }

    public function test_sin_autenticar_no_puede_listar(): void
    {
        $respuesta = $this->getJson('/api/v1/proveedores');

        $respuesta->assertStatus(401);
    }

    public function test_asesor_sin_permiso_no_puede_crear(): void
    {
        $this->actingAsAsesor();

        $respuesta = $this->postJson('/api/v1/proveedores', $this->payloadValido());

        $respuesta->assertStatus(403);
    }
}
