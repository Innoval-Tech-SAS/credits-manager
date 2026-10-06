<?php

namespace Tests\Feature\Respaldos;

use App\Respaldos\Enums\ClaseRespaldo;
use App\Respaldos\Services\RetencionRespaldosService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;
use Tests\Concerns\PreparaRespaldos;
use Tests\TestCase;

class RetencionRespaldosTest extends TestCase
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

    private function ahora(): CarbonImmutable
    {
        return CarbonImmutable::create(2026, 10, 31, 1, 0, 0, config('app.timezone'));
    }

    /** Crea un respaldo vacío cuya carpeta lleva la fecha de `hace` días antes de `ahora()`. */
    private function respaldoHace(ClaseRespaldo $clase, int $dias, ?string $sufijo = null): string
    {
        $nombre = $this->ahora()->subDays($dias)->format('Y-m-d_His').($sufijo ?? '');
        $ruta = $this->raizRespaldos.'/'.$clase->value.'/'.$nombre;
        File::ensureDirectoryExists($ruta);

        return $ruta;
    }

    public function test_el_diario_conserva_siete_dias_y_borra_lo_anterior(): void
    {
        $vigente = $this->respaldoHace(ClaseRespaldo::Diario, 6);
        $enLimite = $this->respaldoHace(ClaseRespaldo::Diario, 7);
        $vencido = $this->respaldoHace(ClaseRespaldo::Diario, 8);

        $eliminados = app(RetencionRespaldosService::class)->aplicar($this->ahora());

        $this->assertSame(2, $eliminados);
        $this->assertDirectoryExists($vigente);
        $this->assertDirectoryDoesNotExist($enLimite);
        $this->assertDirectoryDoesNotExist($vencido);
    }

    public function test_el_semanal_conserva_un_mes(): void
    {
        $vigente = $this->respaldoHace(ClaseRespaldo::Semanal, 30);
        $vencido = $this->respaldoHace(ClaseRespaldo::Semanal, 31);

        app(RetencionRespaldosService::class)->aplicar($this->ahora());

        $this->assertDirectoryExists($vigente);
        $this->assertDirectoryDoesNotExist($vencido);
    }

    public function test_el_mensual_y_el_manual_conservan_un_ano(): void
    {
        $mensualVigente = $this->respaldoHace(ClaseRespaldo::Mensual, 364);
        $mensualVencido = $this->respaldoHace(ClaseRespaldo::Mensual, 365);
        $manualVigente = $this->respaldoHace(ClaseRespaldo::Manual, 364);
        $manualVencido = $this->respaldoHace(ClaseRespaldo::Manual, 365);

        app(RetencionRespaldosService::class)->aplicar($this->ahora());

        $this->assertDirectoryExists($mensualVigente);
        $this->assertDirectoryDoesNotExist($mensualVencido);
        $this->assertDirectoryExists($manualVigente);
        $this->assertDirectoryDoesNotExist($manualVencido);
    }

    public function test_ignora_temporales_y_carpetas_con_otro_nombre(): void
    {
        $temporal = $this->raizRespaldos.'/diario/.tmp-2026-09-01_010000';
        File::ensureDirectoryExists($temporal);

        $ajena = $this->raizRespaldos.'/diario/copia-manual-de-dda';
        File::ensureDirectoryExists($ajena);

        app(RetencionRespaldosService::class)->aplicar($this->ahora());

        $this->assertDirectoryExists($temporal);
        $this->assertDirectoryExists($ajena);
    }

    public function test_la_antiguedad_sale_del_nombre_y_no_de_la_fecha_de_modificacion(): void
    {
        $vencido = $this->respaldoHace(ClaseRespaldo::Diario, 10);
        touch($vencido, $this->ahora()->timestamp);

        app(RetencionRespaldosService::class)->aplicar($this->ahora());

        $this->assertDirectoryDoesNotExist($vencido);
    }

    public function test_no_toca_los_respaldos_de_otras_clases_al_correr_una_clase(): void
    {
        $semanalVencido = $this->respaldoHace(ClaseRespaldo::Semanal, 45);
        $diarioVigente = $this->respaldoHace(ClaseRespaldo::Diario, 1);

        app(RetencionRespaldosService::class)->aplicar($this->ahora());

        $this->assertDirectoryDoesNotExist($semanalVencido);
        $this->assertDirectoryExists($diarioVigente);
    }
}
