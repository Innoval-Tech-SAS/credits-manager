<?php

namespace Tests\Feature\Importacion;

use App\Gastos\Models\ActividadEconomica;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\ImportaArchivos;
use Tests\TestCase;

class ImportacionActividadesEconomicasTest extends TestCase
{
    use ActsAsAdmin;
    use ImportaArchivos;
    use RefreshDatabase;

    private const ENCABEZADO = "Código CIIU;Actividad económica\n";

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararImportaciones();
    }

    public function test_camino_feliz_crea_las_actividades(): void
    {
        $this->actingAsAdmin();

        $id = $this->importarCompleto('actividades_economicas', self::ENCABEZADO
            ."0111;Cultivo de cereales (excepto arroz), legumbres y semillas oleaginosas\n"
            .";Fila sin código\n");

        $this->assertSame(1, $this->importacion($id)->filas_aceptadas);
        $this->assertSame(1, $this->importacion($id)->filas_rechazadas);
        $this->assertDatabaseHas('actividades_economicas', [
            'codigo' => '0111',
            'nombre' => 'Cultivo de cereales (excepto arroz), legumbres y semillas oleaginosas',
        ]);
    }

    public function test_reimportar_un_codigo_existente_actualiza_el_nombre_sin_duplicar(): void
    {
        $this->actingAsAdmin();

        $this->importarCompleto('actividades_economicas', self::ENCABEZADO.'0111;Nombre original'."\n");
        $this->importarCompleto('actividades_economicas', self::ENCABEZADO.'0111;Nombre corregido'."\n");

        $this->assertDatabaseCount('actividades_economicas', 1);
        $this->assertDatabaseHas('actividades_economicas', ['codigo' => '0111', 'nombre' => 'Nombre corregido']);
    }

    public function test_un_admin_no_puede_reutilizar_el_codigo_de_otra_empresa(): void
    {
        $this->actingAsAdmin();
        $this->importarCompleto('actividades_economicas', self::ENCABEZADO.'0111;Actividad de la empresa A'."\n");

        $this->actingAsAdmin();
        $this->importarCompleto('actividades_economicas', self::ENCABEZADO.'0111;Actividad de la empresa B'."\n");

        $this->assertDatabaseCount('actividades_economicas', 2);
        $this->assertSame(2, ActividadEconomica::withoutGlobalScopes()->where('codigo', '0111')->count());
    }
}
