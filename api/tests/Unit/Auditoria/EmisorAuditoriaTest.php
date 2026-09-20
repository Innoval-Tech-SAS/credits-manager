<?php

namespace Tests\Unit\Auditoria;

use App\Auditoria\Constants\AuditoriaConstants;
use App\Auditoria\Enums\AccionAuditoria;
use App\Auditoria\Models\BitacoraAuditoria;
use App\Auditoria\Services\EmisorAuditoria;
use App\Auditoria\Services\RespaldoAuditoriaService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\UsaRespaldoAuditoriaTemporal;
use Tests\TestCase;

class EmisorAuditoriaTest extends TestCase
{
    use ActsAsAdmin;
    use RefreshDatabase;
    use UsaRespaldoAuditoriaTemporal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararRespaldoTemporal();
    }

    protected function tearDown(): void
    {
        $this->limpiarRespaldoTemporal();

        parent::tearDown();
    }

    private function emisor(): EmisorAuditoria
    {
        return app(EmisorAuditoria::class);
    }

    private function entradasRespaldo(): array
    {
        return app(RespaldoAuditoriaService::class)->entradas();
    }

    public function test_publica_el_evento_en_el_stream_con_el_snapshot_del_autor(): void
    {
        $admin = $this->actingAsAdmin();

        $this->emisor()->registrar(AccionAuditoria::Crear, 'clientes', 'Cliente creado', 'cliente', 5, nuevos: ['nombre' => 'Ana']);

        $payloads = $this->redis->payloads(AuditoriaConstants::STREAM);

        $this->assertCount(1, $payloads);
        $this->assertSame('crear', $payloads[0]['accion']);
        $this->assertSame('clientes', $payloads[0]['modulo']);
        $this->assertSame($admin->empresa_id, $payloads[0]['empresa_id']);
        $this->assertSame(['usuario_id' => $admin->id, 'nombre' => $admin->nombre, 'usuario' => $admin->usuario, 'rol' => 'admin', 'es_superadmin' => false], $payloads[0]['autor']);
        $this->assertSame(['nombre' => 'Ana'], $payloads[0]['valores_nuevos']);
    }

    public function test_el_emisor_nunca_inserta_en_la_base_de_datos(): void
    {
        $this->actingAsAdmin();

        $this->emisor()->registrar(AccionAuditoria::Crear, 'clientes', 'Cliente creado');

        $this->assertSame(0, BitacoraAuditoria::query()->count());
    }

    public function test_un_evento_del_superadmin_no_tiene_empresa(): void
    {
        $this->actingAsSuperAdmin();

        $this->emisor()->registrar(AccionAuditoria::Crear, 'empresas', 'Empresa creada', 'empresa', 1);

        $payload = $this->redis->payloads(AuditoriaConstants::STREAM)[0];

        $this->assertNull($payload['empresa_id']);
        $this->assertTrue($payload['autor']['es_superadmin']);
    }

    public function test_acepta_un_autor_explicito_sin_sesion(): void
    {
        $admin = $this->actingAsAdmin();
        $this->salirDeSesion();

        $this->emisor()->registrar(AccionAuditoria::Login, 'auth', 'Inicio de sesión', autor: $admin);

        $this->assertSame($admin->id, $this->redis->payloads(AuditoriaConstants::STREAM)[0]['autor']['usuario_id']);
    }

    public function test_si_redis_esta_caido_el_evento_completo_queda_en_el_log_de_respaldo_sin_interrumpir(): void
    {
        $this->actingAsAdmin();
        $this->redis->caido = true;

        $this->emisor()->registrar(AccionAuditoria::Anular, 'creditos', 'Crédito anulado', 'credito', 8, motivo: 'Error de digitación');

        $entradas = $this->entradasRespaldo();

        $this->assertCount(1, $entradas);
        $this->assertSame('redis_no_disponible', $entradas[0]['motivo_fallo']);
        $this->assertSame('anular', $entradas[0]['evento']['accion']);
        $this->assertSame('Error de digitación', $entradas[0]['evento']['motivo']);
        $this->assertSame($entradas[0]['evento']['evento_id'], $entradas[0]['evento_id']);
        $this->assertSame(0, BitacoraAuditoria::query()->count());
    }

    public function test_un_evento_invalido_no_llega_a_redis_y_va_al_log_de_respaldo(): void
    {
        $this->actingAsAdmin();

        $this->emisor()->registrar(AccionAuditoria::Crear, '', 'Sin módulo');

        $this->assertSame([], $this->redis->payloads(AuditoriaConstants::STREAM));

        $entradas = $this->entradasRespaldo();

        $this->assertCount(1, $entradas);
        $this->assertSame('evento_invalido', $entradas[0]['motivo_fallo']);
        $this->assertNull($entradas[0]['evento']);
        $this->assertSame('Sin módulo', $entradas[0]['payload_crudo']['descripcion']);
    }

    public function test_dentro_de_una_transaccion_el_evento_se_entrega_al_confirmar(): void
    {
        $this->actingAsAdmin();

        DB::transaction(function () {
            $this->emisor()->registrar(AccionAuditoria::Crear, 'clientes', 'Cliente creado');

            $this->assertSame([], $this->redis->payloads(AuditoriaConstants::STREAM));
        });

        $this->assertCount(1, $this->redis->payloads(AuditoriaConstants::STREAM));
    }

    public function test_un_rollback_no_deja_rastro_de_algo_que_no_ocurrio(): void
    {
        $this->actingAsAdmin();

        try {
            DB::transaction(function () {
                $this->emisor()->registrar(AccionAuditoria::Crear, 'clientes', 'Cliente creado');

                throw new Exception('falla de negocio');
            });
        } catch (Exception) {
        }

        $this->assertSame([], $this->redis->payloads(AuditoriaConstants::STREAM));
        $this->assertSame([], $this->entradasRespaldo());
    }
}
