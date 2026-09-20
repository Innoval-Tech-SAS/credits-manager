<?php

namespace Tests\Feature\Gastos;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesGastosFixtures;
use Tests\TestCase;

class TipoGastoControllerTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesGastosFixtures;
    use RefreshDatabase;

    public function test_crea_un_tipo_de_gasto(): void
    {
        $this->actingAsAdmin();

        $respuesta = $this->postJson('/api/v1/gastos/tipos-gasto', [
            'codigo' => 'ARR',
            'nombre' => 'Arriendo',
            'requiere_tercero' => true,
            'aplica_iva' => false,
        ]);

        $respuesta->assertStatus(201)->assertJsonPath('tipo_gasto.codigo', 'ARR');
        $this->assertDatabaseHas('tipos_gasto', ['codigo' => 'ARR', 'nombre' => 'Arriendo']);
    }

    public function test_rechaza_codigo_duplicado_en_la_misma_empresa(): void
    {
        $this->actingAsAdmin();
        $this->crearTipoGasto(['codigo' => 'ARR']);

        $respuesta = $this->postJson('/api/v1/gastos/tipos-gasto', ['codigo' => 'ARR', 'nombre' => 'Arriendo duplicado']);

        $respuesta->assertStatus(422)->assertJsonValidationErrors('codigo');
    }

    public function test_desactiva_un_tipo_de_gasto(): void
    {
        $this->actingAsAdmin();
        $tipoGasto = $this->crearTipoGasto();

        $respuesta = $this->patchJson("/api/v1/gastos/tipos-gasto/{$tipoGasto->id}", ['activo' => false]);

        $respuesta->assertStatus(200)->assertJsonPath('tipo_gasto.activo', false);
    }

    public function test_sin_autenticar_no_puede_listar(): void
    {
        $respuesta = $this->getJson('/api/v1/gastos/tipos-gasto');

        $respuesta->assertStatus(401);
    }

    public function test_asesor_sin_permiso_no_puede_crear(): void
    {
        $this->actingAsAsesor();

        $respuesta = $this->postJson('/api/v1/gastos/tipos-gasto', ['codigo' => 'ARR', 'nombre' => 'Arriendo']);

        $respuesta->assertStatus(403);
    }
}
