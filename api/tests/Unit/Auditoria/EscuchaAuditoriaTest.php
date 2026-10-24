<?php

namespace Tests\Unit\Auditoria;

use App\Auditoria\Constants\AuditoriaConstants;
use App\Auditoria\Data\AutorAuditoria;
use App\Auditoria\Data\EventoAuditoria;
use App\Auditoria\Enums\AccionAuditoria;
use App\Auditoria\Models\BitacoraAuditoria;
use App\Auditoria\Repositories\BitacoraAuditoriaRepository;
use App\Auditoria\Services\DetectorConexionPerdida;
use App\Auditoria\Services\EmisorAuditoria;
use App\Auditoria\Services\EscuchaAuditoria;
use App\Auditoria\Services\RespaldoAuditoriaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\UsaRespaldoAuditoriaTemporal;
use Tests\TestCase;

class EscuchaAuditoriaTest extends TestCase
{
    use ActsAsAdmin;
    use RefreshDatabase;
    use UsaRespaldoAuditoriaTemporal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararRespaldoTemporal();
        $this->redis->crearGrupo(AuditoriaConstants::STREAM, AuditoriaConstants::GRUPO);
    }

    protected function tearDown(): void
    {
        $this->limpiarRespaldoTemporal();

        parent::tearDown();
    }

    private function escucha(): EscuchaAuditoria
    {
        return (new EscuchaAuditoria(
            $this->redis,
            app(BitacoraAuditoriaRepository::class),
            app(RespaldoAuditoriaService::class),
            app(DetectorConexionPerdida::class),
        ))->conEsperaInicial(0);
    }

    private function publicar(?int $empresaId = 1, array $sobrescribir = []): EventoAuditoria
    {
        $evento = EventoAuditoria::crear(...array_merge([
            'accion' => AccionAuditoria::Crear,
            'modulo' => 'clientes',
            'descripcion' => 'Cliente creado',
            'autor' => new AutorAuditoria(null, 'Ana', 'ana', 'admin', false),
            'empresaId' => $empresaId,
        ], $sobrescribir));

        app(EmisorAuditoria::class)->publicar($evento);

        return $evento;
    }

    public function test_inserta_cada_evento_y_lo_confirma(): void
    {
        $empresaA = $this->crearEmpresaDePrueba();
        $empresaB = $this->crearEmpresaDePrueba();
        $this->publicar($empresaA->id);
        $this->publicar($empresaB->id, ['valoresNuevos' => ['a' => 1]]);
        $this->publicar(null, ['autor' => new AutorAuditoria(null, 'Root', 'root', null, true)]);

        $procesadas = $this->escucha()->ciclo('c1', 0);

        $this->assertSame(3, $procesadas);
        $this->assertSame(3, BitacoraAuditoria::query()->count());
        $this->assertSame(0, $this->redis->longitud(AuditoriaConstants::STREAM));
        $this->assertSame(0, $this->redis->pendientes(AuditoriaConstants::STREAM, AuditoriaConstants::GRUPO));
        $this->assertDatabaseHas('bitacora_auditoria', ['empresa_id' => null, 'es_superadmin' => true]);
    }

    public function test_conserva_la_fecha_del_momento_de_la_accion_no_la_de_la_insercion(): void
    {
        $empresa = $this->crearEmpresaDePrueba();
        $evento = $this->publicar($empresa->id, ['fechaHora' => now()->subDays(3)->toImmutable()]);

        $this->escucha()->ciclo('c1', 0);

        $fila = BitacoraAuditoria::query()->where('evento_id', $evento->eventoId)->first();

        $this->assertTrue($fila->fecha_hora->lessThan(now()->subDays(2)));
        $this->assertTrue($fila->creado_en->greaterThan(now()->subMinute()));
    }

    public function test_la_insercion_es_idempotente_por_evento_id(): void
    {
        $empresa = $this->crearEmpresaDePrueba();
        $evento = $this->publicar($empresa->id);
        app(EmisorAuditoria::class)->publicar($evento);

        $this->escucha()->ciclo('c1', 0);

        $this->assertSame(1, BitacoraAuditoria::query()->count());
        $this->assertSame(0, $this->redis->longitud(AuditoriaConstants::STREAM));
    }

    public function test_reclama_y_procesa_lo_que_dejo_pendiente_un_consumidor_muerto(): void
    {
        $empresa = $this->crearEmpresaDePrueba();
        $this->publicar($empresa->id);

        $this->redis->leerNuevas(AuditoriaConstants::STREAM, AuditoriaConstants::GRUPO, 'muerto', 10, 0);
        $this->assertSame(1, $this->redis->pendientes(AuditoriaConstants::STREAM, AuditoriaConstants::GRUPO));

        $this->redis->envejecerPendientes();

        $this->escucha()->ciclo('vivo', 0);

        $this->assertSame(1, BitacoraAuditoria::query()->count());
        $this->assertSame(0, $this->redis->pendientes(AuditoriaConstants::STREAM, AuditoriaConstants::GRUPO));
    }

    public function test_no_le_quita_a_otro_consumidor_lo_que_acaba_de_recibir(): void
    {
        $empresa = $this->crearEmpresaDePrueba();
        $this->publicar($empresa->id);
        $this->redis->leerNuevas(AuditoriaConstants::STREAM, AuditoriaConstants::GRUPO, 'otro', 10, 0);

        $this->assertSame(0, $this->escucha()->ciclo('vivo', 0));
        $this->assertSame(1, $this->redis->pendientes(AuditoriaConstants::STREAM, AuditoriaConstants::GRUPO));
    }

    public function test_un_payload_invalido_va_al_log_de_respaldo_y_no_bloquea_la_cola(): void
    {
        $empresa = $this->crearEmpresaDePrueba();
        $this->redis->agregar(AuditoriaConstants::STREAM, ['evento_id' => 'x', 'payload' => 'esto-no-es-json']);
        $valido = $this->publicar($empresa->id);

        $this->escucha()->ciclo('c1', 0);

        $entradas = app(RespaldoAuditoriaService::class)->entradas();

        $this->assertCount(1, $entradas);
        $this->assertSame('payload_invalido', $entradas[0]['motivo_fallo']);
        $this->assertDatabaseHas('bitacora_auditoria', ['evento_id' => $valido->eventoId]);
        $this->assertSame(0, $this->redis->longitud(AuditoriaConstants::STREAM));
    }

    public function test_un_error_persistente_de_insercion_pasa_al_log_tras_los_reintentos_y_se_confirma(): void
    {
        $repositorio = \Mockery::mock(BitacoraAuditoriaRepository::class);
        $repositorio->shouldReceive('insertarSiNoExiste')->times(AuditoriaConstants::INTENTOS_INSERCION)->andThrow(new \RuntimeException('columna inexistente'));

        $escucha = (new EscuchaAuditoria($this->redis, $repositorio, app(RespaldoAuditoriaService::class), app(DetectorConexionPerdida::class)))->conEsperaInicial(0);

        $evento = $this->publicar(1);
        $escucha->ciclo('c1', 0);

        $entradas = app(RespaldoAuditoriaService::class)->entradas();

        $this->assertCount(1, $entradas);
        $this->assertSame('error_insercion', $entradas[0]['motivo_fallo']);
        $this->assertSame($evento->eventoId, $entradas[0]['evento_id']);
        $this->assertSame($evento->descripcion, $entradas[0]['evento']['descripcion']);
        $this->assertSame(0, $this->redis->longitud(AuditoriaConstants::STREAM));
    }

    public function test_el_listener_no_usa_la_sesion_el_empresa_id_viaja_en_el_evento(): void
    {
        $empresaDelEvento = $this->crearEmpresaDePrueba();
        $this->actingAsAdmin();
        $evento = $this->publicar($empresaDelEvento->id);

        $this->escucha()->ciclo('c1', 0);

        $this->assertDatabaseHas('bitacora_auditoria', ['evento_id' => $evento->eventoId, 'empresa_id' => $empresaDelEvento->id]);
    }
}
