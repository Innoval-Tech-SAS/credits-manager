<?php

namespace Tests\Feature\Importacion;

use App\Causacion\Models\TarifaReteIcaMunicipio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesGastosFixtures;
use Tests\Concerns\ImportaArchivos;
use Tests\TestCase;

class ImportacionTarifasReteicaTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesGastosFixtures;
    use ImportaArchivos;
    use RefreshDatabase;

    private const ENCABEZADO = "Código DANE del municipio;Código CIIU de la actividad;Fecha desde;Tarifa (%)\n";

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararImportaciones();
    }

    public function test_camino_feliz_crea_la_tarifa(): void
    {
        $this->actingAsAdmin();
        $municipio = $this->crearMunicipio(['codigo' => '05001']);
        $actividad = $this->crearActividadEconomica(['codigo' => '0111']);

        $id = $this->importarCompleto('tarifas_reteica', self::ENCABEZADO."05001;0111;01/01/2026;10,5\n");

        $this->assertSame(1, $this->importacion($id)->filas_aceptadas);
        $this->assertDatabaseHas('tarifas_reteica_municipio', [
            'municipio_id' => $municipio->id,
            'actividad_economica_id' => $actividad->id,
            'fecha_desde' => '2026-01-01',
        ]);
        $this->assertSame('10.500', TarifaReteIcaMunicipio::query()->first()->tarifa);
    }

    public function test_el_municipio_inexistente_rechaza_la_fila(): void
    {
        $this->actingAsAdmin();
        $this->crearActividadEconomica(['codigo' => '0111']);

        $id = $this->importarCompleto('tarifas_reteica', self::ENCABEZADO."99999;0111;01/01/2026;10\n");

        $this->assertSame(0, $this->importacion($id)->filas_aceptadas);
        $this->assertStringContainsString('Municipio no encontrado', implode(' ', $this->motivosDeRechazo($id)));
    }

    public function test_la_actividad_de_otra_empresa_no_se_resuelve(): void
    {
        $this->actingAsAdmin();
        $municipio = $this->crearMunicipio(['codigo' => '05001']);

        $this->actingAsAdmin();
        $this->crearActividadEconomica(['codigo' => '0111']);

        $this->actingAsAdmin();
        $id = $this->importarCompleto('tarifas_reteica', self::ENCABEZADO."{$municipio->codigo};0111;01/01/2026;10\n");

        $this->assertSame(0, $this->importacion($id)->filas_aceptadas);
        $this->assertStringContainsString('Actividad económica no encontrada', implode(' ', $this->motivosDeRechazo($id)));
    }
}
