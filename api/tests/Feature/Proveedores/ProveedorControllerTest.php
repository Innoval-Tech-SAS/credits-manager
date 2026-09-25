<?php

namespace Tests\Feature\Proveedores;

use App\Proveedores\Models\Proveedor;
use App\Terceros\Enums\TipoPersona;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesContabilidadFixtures;
use Tests\TestCase;

class ProveedorControllerTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesContabilidadFixtures;
    use RefreshDatabase;

    private const NATURALES = '/api/v1/proveedores/personas-naturales';

    private const JURIDICAS = '/api/v1/proveedores/personas-juridicas';

    private function payloadJuridica(): array
    {
        return [
            'numero_documento' => '900123456',
            'digito_verificacion' => '8',
            'razon_social' => 'Proveedor de prueba S.A.S.',
            'representante_legal' => 'Carlos Andrés Pérez',
            'celular' => '3000000000',
            'correo' => 'proveedor@example.com',
        ];
    }

    private function payloadNatural(): array
    {
        return [
            'tipo_documento' => 'cc',
            'numero_documento' => '1000000001',
            'nombres' => 'Luisa',
            'apellidos' => 'Fernández',
        ];
    }

    private function crearProveedor(TipoPersona $tipoPersona = TipoPersona::Juridica, array $overrides = []): Proveedor
    {
        $tercero = $this->crearTercero(['tipo_persona' => $tipoPersona, ...$overrides]);

        return Proveedor::create(['empresa_id' => $tercero->empresa_id, 'tercero_id' => $tercero->id, 'activo' => true]);
    }

    public function test_crea_un_proveedor_persona_juridica_con_su_tercero(): void
    {
        $this->actingAsAdmin();

        $respuesta = $this->postJson(self::JURIDICAS, $this->payloadJuridica());

        $respuesta->assertStatus(201);
        $this->assertDatabaseHas('terceros', [
            'numero_documento' => '900123456',
            'tipo_persona' => 'juridica',
            'tipo_documento' => 'nit',
            'razon_social' => 'Proveedor de prueba S.A.S.',
            'digito_verificacion' => '8',
            'nombre' => 'Proveedor de prueba S.A.S.',
        ]);
        $this->assertDatabaseHas('proveedores', ['tercero_id' => $respuesta->json('proveedor.tercero_id'), 'activo' => true]);
    }

    public function test_crea_un_proveedor_persona_natural_con_su_tercero(): void
    {
        $this->actingAsAdmin();

        $respuesta = $this->postJson(self::NATURALES, $this->payloadNatural());

        $respuesta->assertStatus(201)->assertJsonPath('proveedor.tercero.nombre', 'Luisa Fernández');
        $this->assertDatabaseHas('terceros', ['numero_documento' => '1000000001', 'tipo_persona' => 'natural', 'nombres' => 'Luisa', 'apellidos' => 'Fernández']);
        $this->assertDatabaseHas('proveedores', ['tercero_id' => $respuesta->json('proveedor.tercero_id'), 'activo' => true]);
    }

    public function test_el_tipo_de_persona_nunca_se_asume_y_lo_define_el_endpoint(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/proveedores', $this->payloadJuridica())->assertStatus(405);
        $this->postJson(self::NATURALES, [...$this->payloadNatural(), 'tipo_persona' => 'juridica'])->assertStatus(201);

        $this->assertDatabaseHas('terceros', ['numero_documento' => '1000000001', 'tipo_persona' => 'natural']);
    }

    public function test_rechaza_datos_de_persona_faltantes(): void
    {
        $this->actingAsAdmin();

        foreach (['razon_social', 'digito_verificacion', 'representante_legal', 'numero_documento'] as $campo) {
            $payload = $this->payloadJuridica();
            unset($payload[$campo]);

            $this->postJson(self::JURIDICAS, $payload)->assertStatus(422)->assertJsonValidationErrors($campo);
        }

        foreach (['tipo_documento', 'nombres', 'apellidos'] as $campo) {
            $payload = $this->payloadNatural();
            unset($payload[$campo]);

            $this->postJson(self::NATURALES, $payload)->assertStatus(422)->assertJsonValidationErrors($campo);
        }
    }

    public function test_rechaza_un_nit_en_persona_natural_y_un_digito_incorrecto_en_persona_juridica(): void
    {
        $this->actingAsAdmin();

        $this->postJson(self::NATURALES, [...$this->payloadNatural(), 'tipo_documento' => 'nit'])->assertStatus(422)->assertJsonValidationErrors('tipo_documento');
        $this->postJson(self::JURIDICAS, [...$this->payloadJuridica(), 'digito_verificacion' => '1'])->assertStatus(422)->assertJsonValidationErrors('digito_verificacion');
    }

    public function test_rechaza_documento_duplicado_en_la_misma_empresa(): void
    {
        $this->actingAsAdmin();
        $this->postJson(self::JURIDICAS, $this->payloadJuridica())->assertStatus(201);

        $respuesta = $this->postJson(self::JURIDICAS, $this->payloadJuridica());

        $respuesta->assertStatus(422)->assertJsonValidationErrors('numero_documento');
    }

    public function test_edita_un_proveedor_persona_juridica(): void
    {
        $this->actingAsAdmin();
        $creado = $this->postJson(self::JURIDICAS, $this->payloadJuridica())->json('proveedor');

        $respuesta = $this->patchJson(self::JURIDICAS."/{$creado['id']}", [
            ...$this->payloadJuridica(),
            'razon_social' => 'Proveedor editado S.A.S.',
        ]);

        $respuesta->assertStatus(200);
        $this->assertDatabaseHas('terceros', ['id' => $creado['tercero_id'], 'razon_social' => 'Proveedor editado S.A.S.', 'nombre' => 'Proveedor editado S.A.S.']);
    }

    public function test_edita_un_proveedor_persona_natural(): void
    {
        $this->actingAsAdmin();
        $creado = $this->postJson(self::NATURALES, $this->payloadNatural())->json('proveedor');

        $respuesta = $this->patchJson(self::NATURALES."/{$creado['id']}", [...$this->payloadNatural(), 'apellidos' => 'Fernández Rojas']);

        $respuesta->assertStatus(200);
        $this->assertDatabaseHas('terceros', ['id' => $creado['tercero_id'], 'apellidos' => 'Fernández Rojas', 'nombre' => 'Luisa Fernández Rojas']);
    }

    public function test_rechaza_edicion_sin_razon_social(): void
    {
        $this->actingAsAdmin();
        $creado = $this->postJson(self::JURIDICAS, $this->payloadJuridica())->json('proveedor');

        $respuesta = $this->patchJson(self::JURIDICAS."/{$creado['id']}", [
            ...$this->payloadJuridica(),
            'razon_social' => '',
        ]);

        $respuesta->assertStatus(422)->assertJsonValidationErrors('razon_social');
    }

    public function test_editar_con_el_endpoint_del_otro_tipo_de_persona_devuelve_404(): void
    {
        $this->actingAsAdmin();
        $natural = $this->crearProveedor(TipoPersona::Natural);
        $juridica = $this->crearProveedor();

        $this->patchJson(self::JURIDICAS."/{$natural->id}", $this->payloadJuridica())->assertStatus(404);
        $this->patchJson(self::NATURALES."/{$juridica->id}", $this->payloadNatural())->assertStatus(404);
    }

    public function test_sin_autenticar_no_puede_crear_ni_editar(): void
    {
        $this->actingAsAdmin();
        $proveedor = $this->crearProveedor();
        $this->salirDeSesion();

        $this->postJson(self::JURIDICAS, $this->payloadJuridica())->assertStatus(401);
        $this->postJson(self::NATURALES, $this->payloadNatural())->assertStatus(401);
        $this->patchJson(self::JURIDICAS."/{$proveedor->id}", $this->payloadJuridica())->assertStatus(401);
    }

    public function test_asesor_sin_permiso_no_puede_crear_ni_editar(): void
    {
        $usuario = $this->actingAsAsesor();
        $proveedor = $this->crearProveedor(TipoPersona::Juridica, ['empresa_id' => $usuario->empresa_id]);

        $this->postJson(self::JURIDICAS, $this->payloadJuridica())->assertStatus(403);
        $this->postJson(self::NATURALES, $this->payloadNatural())->assertStatus(403);
        $this->patchJson(self::JURIDICAS."/{$proveedor->id}", $this->payloadJuridica())->assertStatus(403);
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

        $this->patchJson(self::JURIDICAS.'/999999', $this->payloadJuridica())->assertStatus(404);
        $this->patchJson(self::NATURALES.'/999999', $this->payloadNatural())->assertStatus(404);
    }

    public function test_lista_proveedores_paginados(): void
    {
        $this->actingAsAdmin();
        $this->postJson(self::JURIDICAS, $this->payloadJuridica())->assertStatus(201);
        $this->postJson(self::NATURALES, $this->payloadNatural())->assertStatus(201);

        $respuesta = $this->getJson('/api/v1/proveedores');

        $respuesta->assertStatus(200)->assertJsonCount(2, 'data');
    }

    public function test_sin_autenticar_no_puede_listar(): void
    {
        $respuesta = $this->getJson('/api/v1/proveedores');

        $respuesta->assertStatus(401);
    }
}
