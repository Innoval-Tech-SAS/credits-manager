<?php

namespace Tests\Feature\Respaldos;

use App\Respaldos\Constants\RespaldosConstants;
use App\Respaldos\Enums\ClaseRespaldo;
use App\Respaldos\Services\SubidaRespaldoExternoService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\Concerns\PreparaRespaldos;
use Tests\TestCase;

class SubidaRespaldoExternoTest extends TestCase
{
    use PreparaRespaldos;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararRespaldos();
    }

    protected function tearDown(): void
    {
        $this->limpiarRespaldos();
        parent::tearDown();
    }

    private function sinCredenciales(): void
    {
        config(['filesystems.disks.s3.key' => null, 'filesystems.disks.s3.secret' => null, 'filesystems.disks.s3.bucket' => null]);
    }

    private function conCredenciales(): void
    {
        config([
            'filesystems.disks.s3.key' => 'clave-de-prueba',
            'filesystems.disks.s3.secret' => 'secreto-de-prueba',
            'filesystems.disks.s3.bucket' => 'bucket-de-prueba',
        ]);
    }

    public function test_sin_credenciales_no_sube_nada_y_solo_avisa_en_el_log(): void
    {
        $this->sinCredenciales();
        Log::shouldReceive('warning')->once()->with(Mockery::pattern('/no tiene credenciales configuradas/'));

        app(SubidaRespaldoExternoService::class)->subir('/cualquier/ruta', ClaseRespaldo::Diario);
    }

    public function test_con_credenciales_sube_los_cuatro_archivos_del_respaldo(): void
    {
        $this->conCredenciales();
        Storage::fake('s3');
        $this->travelTo(CarbonImmutable::create(2026, 10, 7, 1, 0, 0, config('app.timezone')));

        $this->artisan('respaldos:ejecutar')->assertExitCode(0);

        $carpeta = '2026-10-07_010000';
        foreach ([RespaldosConstants::ARCHIVO_BASE, RespaldosConstants::ARCHIVO_ARCHIVOS, RespaldosConstants::ARCHIVO_SUMAS, RespaldosConstants::ARCHIVO_DATOS] as $archivo) {
            Storage::disk('s3')->assertExists("diario/{$carpeta}/{$archivo}");
        }
    }

    public function test_un_fallo_al_subir_no_rompe_el_respaldo_local_ni_el_comando(): void
    {
        $this->conCredenciales();
        Storage::fake('s3');
        Log::shouldReceive('warning')->atLeast()->once();

        // Ruta inexistente: file_get_contents falla dentro del servicio, que debe atraparlo sin propagar.
        app(SubidaRespaldoExternoService::class)->subir('/no-existe/ninguna-carpeta', ClaseRespaldo::Diario);

        $this->assertTrue(true);
    }
}
