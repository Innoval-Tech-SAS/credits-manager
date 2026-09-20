<?php

namespace Tests\Feature\Gastos;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesGastosFixtures;
use Tests\TestCase;

class TarifaReteIcaMunicipioControllerTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesGastosFixtures;
    use RefreshDatabase;

    public function test_crea_una_tarifa_vigente(): void
    {
        $this->actingAsAdmin();
        $municipio = $this->crearMunicipio();
        $actividad = $this->crearActividadEconomica();

        $respuesta = $this->postJson('/api/v1/gastos/tarifas-reteica', [
            'municipio_id' => $municipio->id,
            'actividad_economica_id' => $actividad->id,
            'fecha_desde' => '2026-01-01',
            'tarifa' => 6.9,
        ]);

        $respuesta->assertStatus(201);
        $this->assertDatabaseHas('tarifas_reteica_municipio', [
            'municipio_id' => $municipio->id,
            'actividad_economica_id' => $actividad->id,
            'fecha_hasta' => null,
        ]);
    }

    public function test_cierra_la_tarifa_vigente_anterior_al_crear_una_nueva(): void
    {
        $this->actingAsAdmin();
        $municipio = $this->crearMunicipio();
        $actividad = $this->crearActividadEconomica();
        $anterior = $this->crearTarifaReteIca($municipio, $actividad, 5.5, ['fecha_desde' => '2025-01-01']);

        $this->postJson('/api/v1/gastos/tarifas-reteica', [
            'municipio_id' => $municipio->id,
            'actividad_economica_id' => $actividad->id,
            'fecha_desde' => '2026-01-01',
            'tarifa' => 6.9,
        ])->assertStatus(201);

        $this->assertDatabaseHas('tarifas_reteica_municipio', ['id' => $anterior->id, 'fecha_hasta' => '2025-12-31']);
    }
}
