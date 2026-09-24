<?php

namespace Tests\Feature\Geografia;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesGastosFixtures;
use Tests\TestCase;

/**
 * El catálogo geográfico es global a propósito (sin `empresa_id`): lo que debe seguir aislado
 * es lo contable por empresa que apunta a un municipio, como las tarifas ReteICA.
 */
class AislamientoEntreEmpresasTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesGastosFixtures;
    use RefreshDatabase;

    public function test_dos_empresas_comparten_el_municipio_pero_no_sus_tarifas(): void
    {
        $municipio = $this->crearMunicipio();

        $this->actingAsAdmin();
        $actividadA = $this->crearActividadEconomica();
        $this->crearTarifaReteIca($municipio, $actividadA, 6.9);

        $this->actingAsAdmin();
        $this->getJson('/api/v1/gastos/tarifas-reteica')->assertOk()->assertJsonCount(0, 'tarifas');

        $actividadB = $this->crearActividadEconomica();
        $this->postJson('/api/v1/gastos/tarifas-reteica', [
            'municipio_id' => $municipio->id, 'actividad_economica_id' => $actividadB->id, 'fecha_desde' => '2026-01-01', 'tarifa' => 4.14,
        ])->assertCreated();

        $this->getJson('/api/v1/gastos/tarifas-reteica')->assertOk()->assertJsonCount(1, 'tarifas');
    }

    public function test_no_se_crea_una_tarifa_sobre_un_municipio_desactivado(): void
    {
        $municipio = $this->crearMunicipio(['activo' => false]);
        $this->actingAsAdmin();
        $actividad = $this->crearActividadEconomica();

        $this->postJson('/api/v1/gastos/tarifas-reteica', [
            'municipio_id' => $municipio->id, 'actividad_economica_id' => $actividad->id, 'fecha_desde' => '2026-01-01', 'tarifa' => 4.14,
        ])->assertStatus(422)->assertJsonValidationErrors(['municipio_id']);
    }
}
