<?php

namespace Tests\Feature\Geografia;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesGeografiaFixtures;
use Tests\TestCase;

class ConsultarGeografiaTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesGeografiaFixtures;
    use RefreshDatabase;

    public function test_sin_autenticar_no_consulta_el_catalogo(): void
    {
        $this->getJson('/api/v1/geografia/paises')->assertStatus(401);
        $this->getJson('/api/v1/geografia/estados?pais_id=1')->assertStatus(401);
        $this->getJson('/api/v1/geografia/municipios?estado_id=1')->assertStatus(401);
    }

    public function test_lista_los_paises_activos_ordenados_por_nombre(): void
    {
        $this->actingAsAdmin();
        $this->crearPais(['codigo' => 'CO', 'nombre' => 'Colombia']);
        $this->crearPais(['codigo' => 'EC', 'nombre' => 'Ecuador']);
        $this->crearPais(['codigo' => 'PE', 'nombre' => 'Perú', 'activo' => false]);

        $respuesta = $this->getJson('/api/v1/geografia/paises')->assertOk();

        $this->assertSame(['Colombia', 'Ecuador'], collect($respuesta->json('paises'))->pluck('nombre')->all());
    }

    public function test_el_superadmin_tambien_ve_los_inactivos(): void
    {
        $this->actingAsSuperAdmin();
        $this->crearPais(['codigo' => 'CO', 'nombre' => 'Colombia']);
        $this->crearPais(['codigo' => 'PE', 'nombre' => 'Perú', 'activo' => false]);

        $respuesta = $this->getJson('/api/v1/geografia/paises')->assertOk();

        $this->assertCount(2, $respuesta->json('paises'));
    }

    public function test_lista_los_estados_de_un_pais(): void
    {
        $this->actingAsAdmin();
        $pais = $this->crearPais(['codigo' => 'PE', 'nombre' => 'Perú']);
        $otroPais = $this->crearPais(['codigo' => 'EC', 'nombre' => 'Ecuador']);
        $this->crearEstado(['pais_id' => $pais->id, 'nombre' => 'Lima']);
        $this->crearEstado(['pais_id' => $pais->id, 'nombre' => 'Cusco']);
        $this->crearEstado(['pais_id' => $otroPais->id, 'nombre' => 'Guayas']);

        $respuesta = $this->getJson("/api/v1/geografia/estados?pais_id={$pais->id}")->assertOk();

        $this->assertSame(['Cusco', 'Lima'], collect($respuesta->json('estados'))->pluck('nombre')->all());
    }

    public function test_estados_exige_un_pais_existente(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/v1/geografia/estados')->assertStatus(422)->assertJsonValidationErrors(['pais_id']);
        $this->getJson('/api/v1/geografia/estados?pais_id=9999')->assertStatus(422)->assertJsonValidationErrors(['pais_id']);
    }

    public function test_lista_los_municipios_de_un_estado_con_su_estado(): void
    {
        $this->actingAsAdmin();
        $estado = $this->crearEstado(['nombre' => 'Magdalena']);
        $this->crearMunicipio(['estado_id' => $estado->id, 'nombre' => 'Santa Marta', 'es_capital' => true]);
        $this->crearMunicipio(['estado_id' => $estado->id, 'nombre' => 'Ciénaga']);
        $this->crearMunicipio(['nombre' => 'De otro estado']);
        $this->crearMunicipio(['estado_id' => $estado->id, 'nombre' => 'Inactivo', 'activo' => false]);

        $respuesta = $this->getJson("/api/v1/geografia/municipios?estado_id={$estado->id}")->assertOk();

        $this->assertSame(['Ciénaga', 'Santa Marta'], collect($respuesta->json('municipios'))->pluck('nombre')->all());
        $this->assertSame('Magdalena', $respuesta->json('municipios.1.estado.nombre'));
        $this->assertTrue($respuesta->json('municipios.1.es_capital'));
    }

    public function test_busca_municipios_por_texto(): void
    {
        $this->actingAsAdmin();
        $this->crearMunicipio(['nombre' => 'Villa Alta']);
        $this->crearMunicipio(['nombre' => 'Villa Baja']);
        $this->crearMunicipio(['nombre' => 'Barranquilla']);

        $respuesta = $this->getJson('/api/v1/geografia/municipios?buscar=villa')->assertOk();

        $this->assertSame(['Villa Alta', 'Villa Baja'], collect($respuesta->json('municipios'))->pluck('nombre')->all());
    }

    public function test_municipios_exige_estado_o_texto_de_busqueda(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/v1/geografia/municipios')->assertStatus(422)->assertJsonValidationErrors(['estado_id', 'buscar']);
        $this->getJson('/api/v1/geografia/municipios?buscar=s')->assertStatus(422)->assertJsonValidationErrors(['buscar']);
        $this->getJson('/api/v1/geografia/municipios?estado_id=9999')->assertStatus(422)->assertJsonValidationErrors(['estado_id']);
    }
}
