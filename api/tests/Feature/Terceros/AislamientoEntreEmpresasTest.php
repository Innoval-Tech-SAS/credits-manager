<?php

namespace Tests\Feature\Terceros;

use App\Terceros\Enums\TipoPersona;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesContabilidadFixtures;
use Tests\Concerns\CreatesGastosFixtures;
use Tests\TestCase;

class AislamientoEntreEmpresasTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesContabilidadFixtures;
    use CreatesGastosFixtures;
    use RefreshDatabase;

    private const NATURALES = '/api/v1/terceros/personas-naturales';

    private const JURIDICAS = '/api/v1/terceros/personas-juridicas';

    public function test_el_listado_no_muestra_terceros_de_otra_empresa(): void
    {
        $this->actingAsAdmin();
        $this->crearTercero(['nombre' => 'Tercero de la empresa A']);

        $this->actingAsAdmin();
        $this->crearTercero(['nombre' => 'Tercero de la empresa B']);

        $respuesta = $this->getJson('/api/v1/terceros');

        $respuesta->assertStatus(200)->assertJsonCount(1, 'data');
        $this->assertSame('Tercero de la empresa B', $respuesta->json('data.0.nombre'));
    }

    public function test_consultar_o_editar_un_tercero_ajeno_devuelve_404(): void
    {
        $this->actingAsAdmin();
        $ajeno = $this->crearTercero();
        $ajenoNatural = $this->crearTercero(['tipo_persona' => TipoPersona::Natural]);

        $this->actingAsAdmin();

        $this->getJson("/api/v1/terceros/{$ajeno->id}")->assertStatus(404);
        $this->patchJson(self::JURIDICAS."/{$ajeno->id}", ['razon_social' => 'Intento ajeno'])->assertStatus(404);
        $this->patchJson(self::NATURALES."/{$ajenoNatural->id}", ['nombres' => 'Intento'])->assertStatus(404);
        $this->assertDatabaseMissing('terceros', ['razon_social' => 'Intento ajeno']);
    }

    public function test_crear_con_una_actividad_economica_de_otra_empresa_da_422(): void
    {
        $this->actingAsAdmin();
        $actividadAjena = $this->crearActividadEconomica();

        $this->actingAsAdmin();

        $this->postJson(self::JURIDICAS, [
            'numero_documento' => '900123456', 'digito_verificacion' => '8', 'razon_social' => 'Empresa SAS',
            'representante_legal' => 'Rep', 'actividad_economica_id' => $actividadAjena->id,
        ])->assertStatus(422)->assertJsonValidationErrors('actividad_economica_id');
    }

    public function test_el_mismo_documento_puede_existir_en_otra_empresa_sin_revelarlo(): void
    {
        $this->actingAsAdmin();
        $this->crearTercero(['numero_documento' => '900123456']);

        $this->actingAsAdmin();

        $this->postJson(self::JURIDICAS, [
            'numero_documento' => '900123456', 'digito_verificacion' => '8', 'razon_social' => 'Otra Empresa SAS', 'representante_legal' => 'Rep',
        ])->assertStatus(201);
        $this->assertDatabaseCount('terceros', 2);
    }
}
