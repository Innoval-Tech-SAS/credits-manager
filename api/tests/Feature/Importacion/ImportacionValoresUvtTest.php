<?php

namespace Tests\Feature\Importacion;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\ImportaArchivos;
use Tests\TestCase;

class ImportacionValoresUvtTest extends TestCase
{
    use ActsAsAdmin;
    use ImportaArchivos;
    use RefreshDatabase;

    private const ENCABEZADO = "Año;Valor UVT\n";

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararImportaciones();
    }

    public function test_camino_feliz_crea_el_valor_del_anio(): void
    {
        $this->actingAsAdmin();

        $id = $this->importarCompleto('valores_uvt', self::ENCABEZADO."2026;47065\n");

        $this->assertSame(1, $this->importacion($id)->filas_aceptadas);
        $this->assertDatabaseHas('valores_uvt', ['anio' => 2026]);
    }

    public function test_reimportar_el_mismo_anio_reemplaza_el_valor(): void
    {
        $this->actingAsAdmin();

        $this->importarCompleto('valores_uvt', self::ENCABEZADO."2026;47065\n");
        $this->importarCompleto('valores_uvt', self::ENCABEZADO."2026;49799\n");

        $this->assertDatabaseCount('valores_uvt', 1);
        $this->assertDatabaseHas('valores_uvt', ['anio' => 2026, 'valor' => 4979900]);
    }

    public function test_un_valor_en_cero_se_rechaza(): void
    {
        $this->actingAsAdmin();

        $id = $this->importarCompleto('valores_uvt', self::ENCABEZADO."2026;0\n");

        $this->assertSame(0, $this->importacion($id)->filas_aceptadas);
        $this->assertStringContainsString('mayor que cero', implode(' ', $this->motivosDeRechazo($id)));
    }
}
