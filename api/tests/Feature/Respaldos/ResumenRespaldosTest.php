<?php

namespace Tests\Feature\Respaldos;

use App\Auditoria\Enums\AccionAuditoria;
use App\Respaldos\Constants\RespaldosConstants;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\AfirmaAuditoria;
use Tests\Concerns\PreparaRespaldos;
use Tests\TestCase;

class ResumenRespaldosTest extends TestCase
{
    use ActsAsAdmin;
    use AfirmaAuditoria;
    use PreparaRespaldos;
    use RefreshDatabase;

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

    public function test_sin_autenticar_responde_401(): void
    {
        $this->getJson('/api/v1/respaldos/resumen')->assertUnauthorized();
        $this->postJson('/api/v1/respaldos/ejecutar')->assertUnauthorized();
    }

    public function test_el_administrador_de_una_empresa_no_ve_el_resumen(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/v1/respaldos/resumen')->assertForbidden();
        $this->postJson('/api/v1/respaldos/ejecutar')->assertForbidden();
    }

    public function test_el_resumen_reproduce_lo_que_ya_expone_monitoreo_respaldos(): void
    {
        $this->actingAsSuperAdmin();

        $carpeta = now()->subHours(3)->format(RespaldosConstants::FORMATO_CARPETA);
        $this->respaldoEnDisco('diario', $carpeta);

        $resumenNuevo = $this->getJson('/api/v1/respaldos/resumen')->assertOk()->json();
        $resumenExistente = $this->getJson('/api/v1/monitoreo/respaldos')->assertOk()->json();

        $this->assertSame($resumenExistente, $resumenNuevo);
    }

    public function test_ejecutar_crea_el_respaldo_manual_y_queda_en_la_bitacora(): void
    {
        $this->actingAsSuperAdmin();

        $this->postJson('/api/v1/respaldos/ejecutar')->assertOk();

        $this->assertNotEmpty(File::directories($this->raizRespaldos.'/manual'));

        $eventos = $this->eventosDeAuditoria(AccionAuditoria::Crear->value, 'respaldos');
        $this->assertCount(1, $eventos);
        $this->assertNull($eventos[0]['empresa_id']);
    }

    private function respaldoEnDisco(string $clase, string $carpeta): void
    {
        $ruta = $this->raizRespaldos.'/'.$clase.'/'.$carpeta;
        File::ensureDirectoryExists($ruta);

        foreach ([RespaldosConstants::ARCHIVO_BASE, RespaldosConstants::ARCHIVO_ARCHIVOS, RespaldosConstants::ARCHIVO_SUMAS, RespaldosConstants::ARCHIVO_DATOS] as $archivo) {
            file_put_contents($ruta.'/'.$archivo, str_repeat('x', 100));
        }
    }
}
