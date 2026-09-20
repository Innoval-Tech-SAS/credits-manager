<?php

namespace Tests\Feature\Terceros;

use App\Clientes\Models\Cliente;
use App\Empleadores\Models\Empleador;
use App\Terceros\Enums\TipoDocumentoTercero;
use App\Terceros\Models\OtroTercero;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesContabilidadFixtures;
use Tests\TestCase;

class TerceroControllerTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesContabilidadFixtures;
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

    public function test_crea_un_tercero_con_su_extension_otro(): void
    {
        $this->actingAsAdmin();

        $respuesta = $this->postJson('/api/v1/terceros', $this->payloadValido());

        $respuesta->assertStatus(201);
        $this->assertDatabaseHas('terceros', ['numero_documento' => '900123456', 'nombre' => 'Proveedor de prueba S.A.S.']);
        $this->assertDatabaseHas('otro_terceros', ['tercero_id' => $respuesta->json('tercero.id'), 'activo' => true]);
    }

    public function test_reutiliza_un_tercero_existente_sin_extension_otro(): void
    {
        $this->actingAsAdmin();
        $tercero = $this->crearTercero(['tipo_documento' => TipoDocumentoTercero::Nit, 'numero_documento' => '900123456']);

        $respuesta = $this->postJson('/api/v1/terceros', $this->payloadValido());

        $respuesta->assertStatus(201);
        $this->assertDatabaseCount('terceros', 1);
        $this->assertDatabaseHas('otro_terceros', ['tercero_id' => $tercero->id, 'activo' => true]);
    }

    public function test_rechaza_si_el_tercero_ya_tiene_extension_otro(): void
    {
        $this->actingAsAdmin();
        $this->postJson('/api/v1/terceros', $this->payloadValido())->assertStatus(201);

        $respuesta = $this->postJson('/api/v1/terceros', $this->payloadValido());

        $respuesta->assertStatus(422)->assertJsonValidationErrors('numero_documento');
        $this->assertDatabaseCount('otro_terceros', 1);
    }

    public function test_edita_un_tercero(): void
    {
        $this->actingAsAdmin();
        $tercero = $this->crearTercero();

        $respuesta = $this->patchJson("/api/v1/terceros/{$tercero->id}", ['activo' => false]);

        $respuesta->assertStatus(200);
        $this->assertDatabaseHas('terceros', ['id' => $tercero->id, 'activo' => false]);
    }

    public function test_tercero_inexistente_devuelve_404_al_consultar(): void
    {
        $this->actingAsAdmin();

        $respuesta = $this->getJson('/api/v1/terceros/999999');

        $respuesta->assertStatus(404);
    }

    public function test_tercero_inexistente_devuelve_404_al_editar(): void
    {
        $this->actingAsAdmin();

        $respuesta = $this->patchJson('/api/v1/terceros/999999', ['activo' => false]);

        $respuesta->assertStatus(404);
    }

    public function test_lista_terceros_paginados(): void
    {
        $this->actingAsAdmin();
        $this->crearTercero();
        $this->crearTercero();

        $respuesta = $this->getJson('/api/v1/terceros');

        $respuesta->assertStatus(200)->assertJsonCount(2, 'data');
    }

    public function test_el_listado_incluye_el_tipo_de_extension_de_cada_tercero(): void
    {
        $this->actingAsAdmin();
        $terceroSinExtension = $this->crearTercero();
        $terceroOtro = $this->crearTercero();
        OtroTercero::create(['tercero_id' => $terceroOtro->id, 'activo' => true]);
        $terceroCliente = $this->crearTercero();
        $empleador = Empleador::create(['nombre' => 'Empleador X']);
        Cliente::create([
            'tercero_id' => $terceroCliente->id,
            'empleador_id' => $empleador->id,
            'activo' => true,
        ]);

        $respuesta = $this->getJson('/api/v1/terceros?per_page=10');

        $respuesta->assertStatus(200);
        $tipos = collect($respuesta->json('data'))->pluck('tipo', 'id');
        $this->assertNull($tipos[$terceroSinExtension->id]);
        $this->assertSame('otro', $tipos[$terceroOtro->id]);
        $this->assertSame('cliente', $tipos[$terceroCliente->id]);
    }

    public function test_sin_autenticar_no_puede_listar(): void
    {
        $respuesta = $this->getJson('/api/v1/terceros');

        $respuesta->assertStatus(401);
    }

    public function test_asesor_sin_permiso_no_puede_crear(): void
    {
        $this->actingAsAsesor();

        $respuesta = $this->postJson('/api/v1/terceros', $this->payloadValido());

        $respuesta->assertStatus(403);
    }
}
