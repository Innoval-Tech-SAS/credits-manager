<?php

namespace Tests\Feature\Importacion;

use App\Importacion\Models\Importacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\ImportaArchivos;
use Tests\TestCase;

class ImportacionParametrosTest extends TestCase
{
    use ActsAsAdmin;
    use ImportaArchivos;
    use RefreshDatabase;

    private const ENCABEZADO = "Cédula;Nombre completo\n";

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararImportaciones();
    }

    public function test_los_valores_por_defecto_de_los_parametros_son_los_documentados(): void
    {
        $this->assertSame(20, config('importaciones.tamano_maximo_mb'));
        $this->assertSame(500, config('importaciones.tamano_lote'));
        $this->assertSame('/var/importaciones', config('filesystems.disks.importaciones.root'));
    }

    public function test_php_admite_al_menos_el_tamano_maximo_configurado_para_el_archivo(): void
    {
        $bytes = fn (string $valor): int => (int) $valor * (str_ends_with(strtoupper($valor), 'M') ? 1024 * 1024 : 1);

        $this->assertGreaterThanOrEqual(
            config('importaciones.tamano_maximo_mb') * 1024 * 1024,
            $bytes(ini_get('upload_max_filesize')),
            'upload_max_filesize debe cubrir tamano_maximo_mb: sin eso el archivo se pierde antes de llegar a Laravel.',
        );
        $this->assertGreaterThan(
            $bytes(ini_get('upload_max_filesize')),
            $bytes(ini_get('post_max_size')),
            'post_max_size debe superar upload_max_filesize para llevar el resto del formulario.',
        );
    }

    public function test_rechaza_el_archivo_que_supera_el_tamano_maximo_configurado(): void
    {
        $this->actingAsAdmin();
        config(['importaciones.tamano_maximo_mb' => 1]);

        $this->postJson('/api/v1/importaciones', [
            'tipo' => 'clientes',
            'archivo' => UploadedFile::fake()->create('grande.csv', 1025),
        ])->assertUnprocessable()->assertJsonValidationErrors('archivo');

        $this->assertDatabaseCount('importaciones', 0);
    }

    public function test_acepta_el_archivo_justo_en_el_limite_configurado(): void
    {
        $this->actingAsAdmin();
        config(['importaciones.tamano_maximo_mb' => 1]);

        $this->postJson('/api/v1/importaciones', [
            'tipo' => 'clientes',
            'archivo' => UploadedFile::fake()->createWithContent('limite.csv', self::ENCABEZADO.str_repeat("1;Ana Pérez\n", 1)),
        ])->assertStatus(202);

        $this->assertDatabaseCount('importaciones', 1);
    }

    public function test_el_tamano_del_lote_no_cambia_el_resultado_de_la_importacion(): void
    {
        $this->actingAsAdmin();
        $filas = '';
        for ($i = 1; $i <= 7; $i++) {
            $filas .= "900{$i};Persona Número {$i};;;;;;;;\n";
        }
        $csv = self::ENCABEZADO.$filas.";\n;\n".'9008;Solo';

        config(['importaciones.tamano_lote' => 2]);
        $porLotesDe2 = $this->importarCompleto('clientes', $csv, [], 'lotes-2.csv');

        config(['importaciones.tamano_lote' => 500]);
        $porLotesDe500 = $this->importarCompleto('clientes', str_replace('900', '800', $csv), [], 'lotes-500.csv');

        $this->assertSame(7, $this->importacion($porLotesDe2)->filas_aceptadas);
        $this->assertSame(1, $this->importacion($porLotesDe2)->filas_rechazadas);
        $this->assertSame($this->importacion($porLotesDe2)->filas_aceptadas, $this->importacion($porLotesDe500)->filas_aceptadas);
        $this->assertSame($this->importacion($porLotesDe2)->filas_rechazadas, $this->importacion($porLotesDe500)->filas_rechazadas);
        $this->assertDatabaseCount('clientes', 14);
    }

    public function test_la_limpieza_respeta_la_antiguedad_pedida_con_dias(): void
    {
        $this->actingAsAdmin();
        $id = $this->importarCompleto('clientes', self::ENCABEZADO."1001;Ana Pérez\n");
        $ruta = $this->importacion($id)->getRawOriginal('archivo_ruta');
        Importacion::query()->whereKey($id)->update(['creado_en' => now()->subDays(2)]);

        $this->artisan('importaciones:limpiar', ['--dias' => 7])->assertSuccessful();
        Storage::disk('importaciones')->assertExists($ruta);
        $this->assertNotNull($this->importacion($id)->getRawOriginal('archivo_ruta'));

        $this->artisan('importaciones:limpiar', ['--dias' => 1])->assertSuccessful();
        Storage::disk('importaciones')->assertMissing($ruta);
        $this->assertNull($this->importacion($id)->getRawOriginal('archivo_ruta'));
    }

    public function test_la_limpieza_no_toca_importaciones_que_siguen_en_proceso(): void
    {
        $this->actingAsAdmin();
        $id = $this->subirArchivo('clientes', self::ENCABEZADO."1001;Ana Pérez\n");
        $ruta = $this->importacion($id)->getRawOriginal('archivo_ruta');
        Importacion::query()->whereKey($id)->update(['creado_en' => now()->subDays(30)]);

        $this->artisan('importaciones:limpiar', ['--dias' => 1])->assertSuccessful();

        Storage::disk('importaciones')->assertExists($ruta);
        $this->assertNotNull($this->importacion($id)->getRawOriginal('archivo_ruta'));
    }
}
