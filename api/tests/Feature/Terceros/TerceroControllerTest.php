<?php

namespace Tests\Feature\Terceros;

use App\Clientes\Models\Cliente;
use App\Empleadores\Models\Empleador;
use App\Terceros\Enums\TipoDocumentoTercero;
use App\Terceros\Enums\TipoPersona;
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

    private const NATURALES = '/api/v1/terceros/personas-naturales';

    private const JURIDICAS = '/api/v1/terceros/personas-juridicas';

    private function payloadNatural(): array
    {
        return [
            'tipo_documento' => TipoDocumentoTercero::Cc->value,
            'numero_documento' => '1000000001',
            'nombres' => 'María Camila',
            'apellidos' => 'Gómez Ruiz',
            'celular' => '3000000000',
            'correo' => 'maria@example.com',
        ];
    }

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

    public function test_crea_una_persona_natural_con_su_extension_otro(): void
    {
        $this->actingAsAdmin();

        $respuesta = $this->postJson(self::NATURALES, $this->payloadNatural());

        $respuesta->assertStatus(201)->assertJsonPath('tercero.nombre', 'María Camila Gómez Ruiz');
        $this->assertDatabaseHas('terceros', [
            'numero_documento' => '1000000001',
            'tipo_persona' => 'natural',
            'tipo_documento' => 'cc',
            'nombres' => 'María Camila',
            'apellidos' => 'Gómez Ruiz',
            'nombre' => 'María Camila Gómez Ruiz',
            'razon_social' => null,
            'digito_verificacion' => null,
            'representante_legal' => null,
        ]);
        $this->assertDatabaseHas('otro_terceros', ['tercero_id' => $respuesta->json('tercero.id'), 'activo' => true]);
    }

    public function test_crea_una_persona_juridica_con_nit_y_su_extension_otro(): void
    {
        $this->actingAsAdmin();

        $respuesta = $this->postJson(self::JURIDICAS, $this->payloadJuridica());

        $respuesta->assertStatus(201)->assertJsonPath('tercero.nombre', 'Proveedor de prueba S.A.S.');
        $this->assertDatabaseHas('terceros', [
            'numero_documento' => '900123456',
            'tipo_persona' => 'juridica',
            'tipo_documento' => 'nit',
            'razon_social' => 'Proveedor de prueba S.A.S.',
            'digito_verificacion' => '8',
            'representante_legal' => 'Carlos Andrés Pérez',
            'nombre' => 'Proveedor de prueba S.A.S.',
            'nombres' => null,
            'apellidos' => null,
        ]);
        $this->assertDatabaseHas('otro_terceros', ['tercero_id' => $respuesta->json('tercero.id'), 'activo' => true]);
    }

    public function test_la_persona_natural_exige_nombres_apellidos_y_documento(): void
    {
        $this->actingAsAdmin();

        foreach (['tipo_documento', 'numero_documento', 'nombres', 'apellidos'] as $campo) {
            $payload = $this->payloadNatural();
            unset($payload[$campo]);

            $this->postJson(self::NATURALES, $payload)->assertStatus(422)->assertJsonValidationErrors($campo);
        }
    }

    public function test_la_persona_juridica_exige_razon_social_nit_digito_y_representante(): void
    {
        $this->actingAsAdmin();

        foreach (['numero_documento', 'digito_verificacion', 'razon_social', 'representante_legal'] as $campo) {
            $payload = $this->payloadJuridica();
            unset($payload[$campo]);

            $this->postJson(self::JURIDICAS, $payload)->assertStatus(422)->assertJsonValidationErrors($campo);
        }
    }

    public function test_la_persona_natural_no_acepta_nit(): void
    {
        $this->actingAsAdmin();

        $this->postJson(self::NATURALES, [...$this->payloadNatural(), 'tipo_documento' => TipoDocumentoTercero::Nit->value])
            ->assertStatus(422)->assertJsonValidationErrors('tipo_documento');
        $this->assertDatabaseCount('terceros', 0);
    }

    public function test_la_persona_juridica_ignora_un_tipo_de_documento_distinto_de_nit(): void
    {
        $this->actingAsAdmin();

        $this->postJson(self::JURIDICAS, [...$this->payloadJuridica(), 'tipo_documento' => 'cc'])->assertStatus(201);

        $this->assertDatabaseHas('terceros', ['numero_documento' => '900123456', 'tipo_documento' => 'nit']);
    }

    public function test_rechaza_un_digito_de_verificacion_que_no_corresponde_al_nit(): void
    {
        $this->actingAsAdmin();

        $this->postJson(self::JURIDICAS, [...$this->payloadJuridica(), 'digito_verificacion' => '3'])
            ->assertStatus(422)->assertJsonValidationErrors('digito_verificacion');
        $this->assertDatabaseCount('terceros', 0);
    }

    public function test_el_nit_debe_ser_solo_digitos(): void
    {
        $this->actingAsAdmin();

        $this->postJson(self::JURIDICAS, [...$this->payloadJuridica(), 'numero_documento' => '900.123.456-8'])
            ->assertStatus(422)->assertJsonValidationErrors('numero_documento');
    }

    public function test_reutiliza_un_tercero_existente_sin_extension_otro(): void
    {
        $this->actingAsAdmin();
        $tercero = $this->crearTercero(['numero_documento' => '900123456']);

        $respuesta = $this->postJson(self::JURIDICAS, $this->payloadJuridica());

        $respuesta->assertStatus(201);
        $this->assertDatabaseCount('terceros', 1);
        $this->assertDatabaseHas('otro_terceros', ['tercero_id' => $tercero->id, 'activo' => true]);
    }

    public function test_rechaza_si_el_tercero_ya_tiene_extension_otro(): void
    {
        $this->actingAsAdmin();
        $this->postJson(self::JURIDICAS, $this->payloadJuridica())->assertStatus(201);

        $respuesta = $this->postJson(self::JURIDICAS, $this->payloadJuridica());

        $respuesta->assertStatus(422)->assertJsonValidationErrors('numero_documento');
        $this->assertDatabaseCount('otro_terceros', 1);
    }

    public function test_edita_una_persona_natural(): void
    {
        $this->actingAsAdmin();
        $tercero = $this->crearTercero(['tipo_persona' => TipoPersona::Natural]);

        $respuesta = $this->patchJson(self::NATURALES."/{$tercero->id}", ['nombres' => 'Ana', 'apellidos' => 'Torres', 'activo' => false]);

        $respuesta->assertStatus(200)->assertJsonPath('tercero.nombre', 'Ana Torres');
        $this->assertDatabaseHas('terceros', ['id' => $tercero->id, 'nombres' => 'Ana', 'apellidos' => 'Torres', 'nombre' => 'Ana Torres', 'activo' => false]);
    }

    public function test_edita_una_persona_juridica_y_recalcula_el_nombre(): void
    {
        $this->actingAsAdmin();
        $tercero = $this->crearTercero();

        $respuesta = $this->patchJson(self::JURIDICAS."/{$tercero->id}", [
            'razon_social' => 'Razón Social Nueva S.A.S.',
            'representante_legal' => 'Nuevo Representante',
        ]);

        $respuesta->assertStatus(200);
        $this->assertDatabaseHas('terceros', ['id' => $tercero->id, 'razon_social' => 'Razón Social Nueva S.A.S.', 'nombre' => 'Razón Social Nueva S.A.S.']);
    }

    public function test_editar_rechaza_el_documento_de_otro_tercero_de_la_misma_empresa(): void
    {
        $this->actingAsAdmin();
        $this->crearTercero(['numero_documento' => '900123456']);
        $tercero = $this->crearTercero(['numero_documento' => '800197268']);

        $this->patchJson(self::JURIDICAS."/{$tercero->id}", ['numero_documento' => '900123456', 'digito_verificacion' => '8'])
            ->assertStatus(422)->assertJsonValidationErrors('numero_documento');
    }

    public function test_editar_con_el_endpoint_del_otro_tipo_de_persona_devuelve_404(): void
    {
        $this->actingAsAdmin();
        $natural = $this->crearTercero(['tipo_persona' => TipoPersona::Natural]);
        $juridica = $this->crearTercero();

        $this->patchJson(self::JURIDICAS."/{$natural->id}", ['activo' => false])->assertStatus(404);
        $this->patchJson(self::NATURALES."/{$juridica->id}", ['activo' => false])->assertStatus(404);
        $this->assertDatabaseMissing('terceros', ['activo' => false]);
    }

    public function test_el_tipo_de_persona_no_se_puede_cambiar_desde_el_payload(): void
    {
        $this->actingAsAdmin();
        $tercero = $this->crearTercero();

        $this->patchJson(self::JURIDICAS."/{$tercero->id}", ['tipo_persona' => 'natural', 'activo' => false])->assertStatus(200);

        $this->assertDatabaseHas('terceros', ['id' => $tercero->id, 'tipo_persona' => 'juridica']);
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

        $this->patchJson(self::NATURALES.'/999999', ['activo' => false])->assertStatus(404);
        $this->patchJson(self::JURIDICAS.'/999999', ['activo' => false])->assertStatus(404);
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
        $admin = $this->actingAsAdmin();
        $terceroSinExtension = $this->crearTercero();
        $terceroOtro = $this->crearTercero();
        OtroTercero::create(['tercero_id' => $terceroOtro->id, 'activo' => true]);
        $terceroCliente = $this->crearTercero(['tipo_persona' => TipoPersona::Natural]);
        $empleador = Empleador::create(['nombre' => 'Empleador X']);
        Cliente::create([
            'tercero_id' => $terceroCliente->id,
            'empleador_id' => $empleador->id,
            'asesor_id' => $admin->id,
            'activo' => true,
        ]);

        $respuesta = $this->getJson('/api/v1/terceros?per_page=10');

        $respuesta->assertStatus(200);
        $tipos = collect($respuesta->json('data'))->pluck('tipo', 'id');
        $this->assertNull($tipos[$terceroSinExtension->id]);
        $this->assertSame('otro', $tipos[$terceroOtro->id]);
        $this->assertSame('cliente', $tipos[$terceroCliente->id]);
    }

    public function test_sin_autenticar_no_puede_crear_ni_editar_ni_listar(): void
    {
        $this->postJson(self::NATURALES, $this->payloadNatural())->assertStatus(401);
        $this->postJson(self::JURIDICAS, $this->payloadJuridica())->assertStatus(401);
        $this->patchJson(self::NATURALES.'/1', ['activo' => false])->assertStatus(401);
        $this->patchJson(self::JURIDICAS.'/1', ['activo' => false])->assertStatus(401);
        $this->getJson('/api/v1/terceros')->assertStatus(401);
    }

    public function test_asesor_sin_permiso_no_puede_crear_ni_editar(): void
    {
        $usuario = $this->actingAsAsesor();
        $tercero = $this->crearTercero(['empresa_id' => $usuario->empresa_id]);

        $this->postJson(self::NATURALES, $this->payloadNatural())->assertStatus(403);
        $this->postJson(self::JURIDICAS, $this->payloadJuridica())->assertStatus(403);
        $this->patchJson(self::JURIDICAS."/{$tercero->id}", ['activo' => false])->assertStatus(403);
        $this->assertDatabaseCount('otro_terceros', 0);
    }
}
