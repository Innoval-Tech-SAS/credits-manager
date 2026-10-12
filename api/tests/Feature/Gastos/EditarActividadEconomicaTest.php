<?php

namespace Tests\Feature\Gastos;

use App\Empresas\Models\Empresa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesGastosFixtures;
use Tests\TestCase;

class EditarActividadEconomicaTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesGastosFixtures;
    use RefreshDatabase;

    private const BASE = '/api/v1/gastos/actividades-economicas';

    public function test_editar_conservando_el_mismo_codigo_no_choca_consigo_misma(): void
    {
        $this->actingAsAdmin();
        $actividad = $this->crearActividadEconomica(['codigo' => '6492', 'nombre' => 'Crédito']);

        $this->patchJson(self::BASE."/{$actividad->id}", ['codigo' => '6492', 'nombre' => 'Otras actividades de crédito', 'activo' => false])
            ->assertOk();

        $this->assertDatabaseHas('actividades_economicas', ['id' => $actividad->id, 'codigo' => '6492', 'nombre' => 'Otras actividades de crédito', 'activo' => false]);
    }

    public function test_rechaza_el_codigo_de_otra_actividad_de_la_empresa(): void
    {
        $this->actingAsAdmin();
        $this->crearActividadEconomica(['codigo' => '6492']);
        $actividad = $this->crearActividadEconomica(['codigo' => '6499']);

        $this->patchJson(self::BASE."/{$actividad->id}", ['codigo' => '6492'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('codigo');
    }

    public function test_sin_permiso_de_edicion_responde_403(): void
    {
        $admin = $this->actingAsAdmin();
        $actividad = $this->crearActividadEconomica();
        $this->actingAsAsesor(Empresa::findOrFail($admin->empresa_id));

        $this->patchJson(self::BASE."/{$actividad->id}", ['nombre' => 'Intento'])->assertForbidden();
    }
}
