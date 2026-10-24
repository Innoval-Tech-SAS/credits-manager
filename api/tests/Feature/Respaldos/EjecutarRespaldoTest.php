<?php

namespace Tests\Feature\Respaldos;

use App\Respaldos\Constants\RespaldosConstants;
use App\Respaldos\Services\VolcadoBaseDatosService;
use Carbon\CarbonImmutable;
use Database\Seeders\ConfiguracionRespaldoExternoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Tests\Concerns\PreparaRespaldos;
use Tests\TestCase;

class EjecutarRespaldoTest extends TestCase
{
    use PreparaRespaldos, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ConfiguracionRespaldoExternoSeeder::class);
        $this->prepararRespaldos();
    }

    protected function tearDown(): void
    {
        $this->limpiarRespaldos();
        parent::tearDown();
    }

    /** Miércoles 7 de octubre de 2026, 01:00 en Bogotá: solo copia diaria. */
    private function enFecha(int $anio, int $mes, int $dia): void
    {
        $this->travelTo(CarbonImmutable::create($anio, $mes, $dia, 1, 0, 0, config('app.timezone')));
    }

    private function carpetaDelDia(string $clase, int $anio, int $mes, int $dia): string
    {
        return $this->raizRespaldos.'/'.$clase.'/'.sprintf('%04d-%02d-%02d_010000', $anio, $mes, $dia);
    }

    public function test_crea_el_respaldo_diario_con_base_archivos_sumas_y_ficha(): void
    {
        $this->enFecha(2026, 10, 7);

        $this->artisan('respaldos:ejecutar')->assertExitCode(0);

        $carpeta = $this->carpetaDelDia('diario', 2026, 10, 7);
        $this->assertFileExists($carpeta.'/'.RespaldosConstants::ARCHIVO_BASE);
        $this->assertFileExists($carpeta.'/'.RespaldosConstants::ARCHIVO_ARCHIVOS);
        $this->assertFileExists($carpeta.'/'.RespaldosConstants::ARCHIVO_SUMAS);
        $this->assertFileExists($carpeta.'/'.RespaldosConstants::ARCHIVO_DATOS);

        $this->assertDirectoryDoesNotExist($this->raizRespaldos.'/diario/'.RespaldosConstants::PREFIJO_TEMPORAL.'2026-10-07_010000');
        $this->assertDirectoryDoesNotExist($this->raizRespaldos.'/semanal');
        $this->assertDirectoryDoesNotExist($this->raizRespaldos.'/mensual');
    }

    public function test_las_sumas_coinciden_con_los_archivos_en_formato_sha256sum(): void
    {
        $this->enFecha(2026, 10, 7);
        $this->artisan('respaldos:ejecutar')->assertExitCode(0);

        $carpeta = $this->carpetaDelDia('diario', 2026, 10, 7);
        $lineas = file($carpeta.'/'.RespaldosConstants::ARCHIVO_SUMAS, FILE_IGNORE_NEW_LINES);

        $this->assertCount(2, $lineas);
        foreach ($lineas as $linea) {
            [$hash, $archivo] = explode('  ', $linea);
            $this->assertSame(hash_file('sha256', $carpeta.'/'.$archivo), $hash);
        }
    }

    public function test_el_archivo_contiene_las_carpetas_de_origen_con_su_nombre(): void
    {
        $this->enFecha(2026, 10, 7);
        $this->artisan('respaldos:ejecutar')->assertExitCode(0);

        $proceso = new Process(['tar', '-tzf', $this->carpetaDelDia('diario', 2026, 10, 7).'/'.RespaldosConstants::ARCHIVO_ARCHIVOS]);
        $proceso->mustRun();

        $this->assertStringContainsString('app/privado/archivo.txt', $proceso->getOutput());
        $this->assertStringContainsString('exportaciones/privado/archivo.txt', $proceso->getOutput());
    }

    public function test_la_ficha_no_lleva_credenciales_del_motor(): void
    {
        $this->enFecha(2026, 10, 7);
        $this->artisan('respaldos:ejecutar')->assertExitCode(0);

        $ficha = file_get_contents($this->carpetaDelDia('diario', 2026, 10, 7).'/'.RespaldosConstants::ARCHIVO_DATOS);

        $this->assertStringNotContainsString('clave-secreta-de-prueba', $ficha);
        $this->assertStringContainsString('"clase": "diario"', $ficha);
    }

    public function test_el_domingo_tambien_copia_a_semanal(): void
    {
        $this->enFecha(2026, 10, 4);

        $this->artisan('respaldos:ejecutar')->assertExitCode(0);

        $this->assertFileExists($this->carpetaDelDia('diario', 2026, 10, 4).'/'.RespaldosConstants::ARCHIVO_BASE);
        $this->assertFileExists($this->carpetaDelDia('semanal', 2026, 10, 4).'/'.RespaldosConstants::ARCHIVO_BASE);
        $this->assertDirectoryDoesNotExist($this->raizRespaldos.'/mensual');
    }

    public function test_el_dia_1_tambien_copia_a_mensual(): void
    {
        $this->enFecha(2026, 12, 1);

        $this->artisan('respaldos:ejecutar')->assertExitCode(0);

        $this->assertFileExists($this->carpetaDelDia('mensual', 2026, 12, 1).'/'.RespaldosConstants::ARCHIVO_BASE);
        $this->assertDirectoryDoesNotExist($this->raizRespaldos.'/semanal');
    }

    public function test_el_primer_domingo_del_mes_copia_a_las_tres_clases(): void
    {
        $this->enFecha(2026, 11, 1);

        $this->artisan('respaldos:ejecutar')->assertExitCode(0);

        foreach (['diario', 'semanal', 'mensual'] as $clase) {
            $this->assertFileExists($this->carpetaDelDia($clase, 2026, 11, 1).'/'.RespaldosConstants::ARCHIVO_BASE);
        }
    }

    public function test_el_manual_solo_guarda_en_la_carpeta_manual(): void
    {
        $this->enFecha(2026, 10, 7);

        $this->artisan('respaldos:ejecutar --manual')
            ->expectsOutputToContain('Respaldo manual creado')
            ->assertExitCode(0);

        $this->assertFileExists($this->carpetaDelDia('manual', 2026, 10, 7).'/'.RespaldosConstants::ARCHIVO_BASE);
        $this->assertDirectoryDoesNotExist($this->raizRespaldos.'/diario');
    }

    public function test_aplica_la_retencion_tras_un_respaldo_correcto(): void
    {
        $this->enFecha(2026, 10, 7);
        $vencido = $this->carpetaDelDia('diario', 2026, 9, 29);
        File::ensureDirectoryExists($vencido);

        $this->artisan('respaldos:ejecutar')->assertExitCode(0);

        $this->assertDirectoryDoesNotExist($vencido);
        $this->assertDirectoryExists($this->carpetaDelDia('diario', 2026, 10, 7));
    }

    public function test_un_fallo_del_volcado_no_deja_respaldo_ni_temporal_y_no_muestra_la_clave(): void
    {
        $this->enFecha(2026, 10, 7);
        config(['respaldos.binario_volcado' => '/no-existe/mariadb-dump']);
        $this->app->instance(VolcadoBaseDatosService::class, new VolcadoBaseDatosService);

        $this->artisan('respaldos:ejecutar')
            ->expectsOutputToContain('El respaldo falló')
            ->doesntExpectOutputToContain('clave-secreta-de-prueba')
            ->assertExitCode(1);

        $this->assertSame([], File::directories($this->raizRespaldos.'/diario'));
        $this->assertFileDoesNotExist($this->raizRespaldos.'/diario/'.RespaldosConstants::PREFIJO_TEMPORAL.'2026-10-07_010000/'.RespaldosConstants::ARCHIVO_BASE);
    }

    public function test_no_sobrescribe_un_respaldo_de_la_misma_fecha(): void
    {
        $this->enFecha(2026, 10, 7);
        File::ensureDirectoryExists($this->carpetaDelDia('diario', 2026, 10, 7));

        $this->artisan('respaldos:ejecutar')
            ->expectsOutputToContain('Ya existe un respaldo')
            ->assertExitCode(1);
    }
}
