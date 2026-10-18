<?php

namespace Tests\Feature\Respaldos;

use App\Respaldos\Constants\RespaldosConstants;
use App\Respaldos\Enums\ClaseRespaldo;
use App\Respaldos\Services\SubidaRespaldoExternoService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\Concerns\PreparaRespaldos;
use Tests\Concerns\SimulaRespaldoExterno;
use Tests\TestCase;

class SubidaRespaldoExternoTest extends TestCase
{
    use PreparaRespaldos, SimulaRespaldoExterno;

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

    public function test_sin_activar_no_sube_nada_y_solo_avisa_en_el_log(): void
    {
        Log::shouldReceive('warning')->once()->with(Mockery::pattern('/sin configuración activa/'));

        app(SubidaRespaldoExternoService::class)->subir('/cualquier/ruta', ClaseRespaldo::Diario);
    }

    public function test_activa_sube_los_cuatro_archivos_del_respaldo(): void
    {
        $this->activarRespaldoExterno();
        $disco = $this->simulaRespaldoExterno();
        $this->travelTo(CarbonImmutable::create(2026, 10, 7, 1, 0, 0, config('app.timezone')));

        $this->artisan('respaldos:ejecutar')->assertExitCode(0);

        $carpeta = '2026-10-07_010000';
        foreach ([RespaldosConstants::ARCHIVO_BASE, RespaldosConstants::ARCHIVO_ARCHIVOS, RespaldosConstants::ARCHIVO_SUMAS, RespaldosConstants::ARCHIVO_DATOS] as $archivo) {
            $disco->assertExists("diario/{$carpeta}/{$archivo}");
        }
    }

    public function test_un_fallo_al_subir_no_rompe_el_respaldo_local_ni_el_comando(): void
    {
        $this->activarRespaldoExterno();
        $this->simulaRespaldoExterno();
        Log::shouldReceive('warning')->atLeast()->once();

        // Ruta inexistente: file_get_contents falla dentro del servicio, que debe atraparlo sin propagar.
        app(SubidaRespaldoExternoService::class)->subir('/no-existe/ninguna-carpeta', ClaseRespaldo::Diario);

        $this->assertTrue(true);
    }
}
