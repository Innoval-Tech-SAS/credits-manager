<?php

namespace Tests\Unit\Auditoria;

use App\Auditoria\Constants\AuditoriaConstants;
use App\Auditoria\Data\AutorAuditoria;
use App\Auditoria\Data\EventoAuditoria;
use App\Auditoria\Enums\AccionAuditoria;
use App\Auditoria\Repositories\BitacoraAuditoriaRepository;
use App\Auditoria\Services\DetectorConexionPerdida;
use App\Auditoria\Services\EmisorAuditoria;
use App\Auditoria\Services\EscuchaAuditoria;
use App\Auditoria\Services\RespaldoAuditoriaService;
use Illuminate\Database\QueryException;
use Mockery;
use PDOException;
use Tests\Concerns\UsaRespaldoAuditoriaTemporal;
use Tests\TestCase;

/** Sin RefreshDatabase: el listener hace DB::purge() al perder la conexión y eso destruiría la BD SQLite en memoria de la suite. */
class EscuchaAuditoriaConexionTest extends TestCase
{
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

    private function conexionPerdida(): QueryException
    {
        return new QueryException('mysql', 'insert into bitacora_auditoria ...', [], new PDOException('SQLSTATE[HY000]: General error: 2006 MySQL server has gone away'));
    }

    private function publicar(): EventoAuditoria
    {
        $evento = EventoAuditoria::crear(AccionAuditoria::Crear, 'clientes', 'Cliente creado', new AutorAuditoria(null, 'Ana', 'ana', 'admin', false), 1);
        app(EmisorAuditoria::class)->publicar($evento);

        return $evento;
    }

    private function escucha(BitacoraAuditoriaRepository $repositorio): EscuchaAuditoria
    {
        return (new EscuchaAuditoria($this->redis, $repositorio, app(RespaldoAuditoriaService::class), app(DetectorConexionPerdida::class)))->conEsperaInicial(0);
    }

    public function test_con_la_base_caida_reintenta_sin_confirmar_y_al_volver_inserta_y_confirma(): void
    {
        $repositorio = Mockery::mock(BitacoraAuditoriaRepository::class);
        $repositorio->shouldReceive('insertarSiNoExiste')->twice()->andReturnUsing(
            function () {
                static $llamadas = 0;

                if (++$llamadas === 1) {
                    throw $this->conexionPerdida();
                }

                return true;
            },
        );

        $this->publicar();

        $this->assertSame(1, $this->escucha($repositorio)->ciclo('c1', 0));
        $this->assertSame(0, $this->redis->longitud(AuditoriaConstants::STREAM));
        $this->assertSame([], app(RespaldoAuditoriaService::class)->entradas());
    }

    public function test_si_se_pide_detener_durante_la_caida_el_evento_queda_pendiente_no_se_pierde(): void
    {
        $escucha = null;
        $repositorio = Mockery::mock(BitacoraAuditoriaRepository::class);
        $repositorio->shouldReceive('insertarSiNoExiste')->once()->andReturnUsing(function () use (&$escucha) {
            $escucha->solicitarParada();

            throw $this->conexionPerdida();
        });

        $escucha = $this->escucha($repositorio);
        $this->publicar();

        $this->assertSame(0, $escucha->ciclo('c1', 0));
        $this->assertSame(1, $this->redis->longitud(AuditoriaConstants::STREAM));
        $this->assertSame(1, $this->redis->pendientes(AuditoriaConstants::STREAM, AuditoriaConstants::GRUPO));
        $this->assertSame([], app(RespaldoAuditoriaService::class)->entradas());
    }
}
