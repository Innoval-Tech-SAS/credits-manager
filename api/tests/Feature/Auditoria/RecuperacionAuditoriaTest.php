<?php

namespace Tests\Feature\Auditoria;

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

class RecuperacionAuditoriaTest extends TestCase
{
    use ActsAsAdmin;
    use RefreshDatabase;
    use UsaRespaldoAuditoriaTemporal;

    private const URL = '/api/v1/auditoria/respaldo';

    private int $empresaId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararRespaldoTemporal();
        $this->empresaId = $this->crearEmpresaDePrueba()->id;
    }

    protected function tearDown(): void
    {
        $this->limpiarRespaldoTemporal();

        parent::tearDown();
    }

    /** Deja un evento en el log de respaldo, como pasaría con Redis caído. */
    private function eventoEnRespaldo(string $descripcion = 'Cliente creado', ?int $empresaId = null): EventoAuditoria
    {
        $evento = EventoAuditoria::crear(
            AccionAuditoria::Crear, 'clientes', $descripcion, new AutorAuditoria(4, 'Ana', 'ana', 'admin', false), $empresaId ?? $this->empresaId,
            ip: '10.1.1.1', fechaHora: now()->subHours(2)->toImmutable(),
        );

        $this->redis->caido = true;
        app(EmisorAuditoria::class)->emitir($evento);
        $this->redis->caido = false;

        return $evento;
    }

    private function escucha(): EscuchaAuditoria
    {
        $this->redis->crearGrupo(AuditoriaConstants::STREAM, AuditoriaConstants::GRUPO);

        return (new EscuchaAuditoria($this->redis, app(BitacoraAuditoriaRepository::class), app(RespaldoAuditoriaService::class), app(DetectorConexionPerdida::class)))->conEsperaInicial(0);
    }

    public function test_sin_autenticar_recibe_401(): void
    {
        $this->getJson(self::URL)->assertStatus(401);
        $this->postJson(self::URL.'/recuperar', ['todos' => true])->assertStatus(401);
    }

    public function test_un_admin_de_empresa_no_accede_al_panel_de_recuperacion(): void
    {
        $this->actingAsAdmin();

        $this->getJson(self::URL)->assertStatus(403);
        $this->postJson(self::URL.'/recuperar', ['todos' => true])->assertStatus(403);
    }

    public function test_lista_el_respaldo_con_su_estado(): void
    {
        $this->actingAsSuperAdmin();

        $pendiente = $this->eventoEnRespaldo('Pendiente de recuperar');
        $recuperado = $this->eventoEnRespaldo('Ya recuperado');
        app(BitacoraAuditoriaRepository::class)->insertarSiNoExiste($recuperado);

        $this->redis->caido = false;
        app(EmisorAuditoria::class)->registrar(AccionAuditoria::Crear, '', 'Inválido');

        $filas = collect($this->getJson(self::URL)->assertOk()->json('data'))->keyBy('descripcion');

        $this->assertSame('pendiente', $filas['Pendiente de recuperar']['estado']);
        $this->assertSame($pendiente->eventoId, $filas['Pendiente de recuperar']['evento_id']);
        $this->assertSame('redis_no_disponible', $filas['Pendiente de recuperar']['motivo_fallo']);
        $this->assertSame('recuperado', $filas['Ya recuperado']['estado']);
        $this->assertSame('no_recuperable', $filas['Inválido']['estado']);
    }

    public function test_filtra_por_estado(): void
    {
        $this->actingAsSuperAdmin();
        $this->eventoEnRespaldo('Uno');
        $recuperado = $this->eventoEnRespaldo('Dos');
        app(BitacoraAuditoriaRepository::class)->insertarSiNoExiste($recuperado);

        $this->getJson(self::URL.'?estado=pendiente')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.descripcion', 'Uno');
        $this->getJson(self::URL.'?estado=inventado')->assertStatus(422)->assertJsonValidationErrors('estado');
    }

    public function test_recuperar_reemite_el_evento_original_y_el_listener_lo_inserta_una_sola_vez(): void
    {
        $superadmin = $this->actingAsSuperAdmin();
        $original = $this->eventoEnRespaldo('Se perdió en la caída');

        $this->postJson(self::URL.'/recuperar', ['todos' => true])->assertOk()->assertExactJson(['recuperados' => 1, 'fallidos' => 0]);

        $payloads = $this->redis->payloads(AuditoriaConstants::STREAM);
        $reemitido = collect($payloads)->firstWhere('evento_id', $original->eventoId);

        $this->assertSame($original->fechaHora->format('Y-m-d\TH:i:s.vP'), $reemitido['fecha_hora']);
        $this->assertSame(4, $reemitido['autor']['usuario_id']);
        $this->assertSame('10.1.1.1', $reemitido['ip']);

        $recuperacion = collect($payloads)->firstWhere('accion', 'recuperar');
        $this->assertSame($superadmin->id, $recuperacion['autor']['usuario_id']);
        $this->assertSame([$original->eventoId], $recuperacion['valores_nuevos']['evento_ids']);

        // Aunque se recupere dos veces (el evento sigue pendiente hasta que el listener lo inserta), la inserción es idempotente por evento_id.
        $this->postJson(self::URL.'/recuperar', ['evento_ids' => [$original->eventoId]])->assertOk();
        $this->escucha()->ciclo('c1', 0);

        $this->assertSame(1, BitacoraAuditoria::query()->where('evento_id', $original->eventoId)->count());
        $this->assertDatabaseHas('bitacora_auditoria', ['evento_id' => $original->eventoId, 'descripcion' => 'Se perdió en la caída']);
        $this->assertSame(2, BitacoraAuditoria::query()->where('accion', 'recuperar')->count());

        $this->getJson(self::URL.'?estado=pendiente')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_recuperar_solo_los_ids_indicados(): void
    {
        $this->actingAsSuperAdmin();
        $uno = $this->eventoEnRespaldo('Uno');
        $this->eventoEnRespaldo('Dos');

        $this->postJson(self::URL.'/recuperar', ['evento_ids' => [$uno->eventoId]])->assertOk()->assertJsonPath('recuperados', 1);

        $this->assertNotNull(collect($this->redis->payloads(AuditoriaConstants::STREAM))->firstWhere('evento_id', $uno->eventoId));
        $this->assertNull(collect($this->redis->payloads(AuditoriaConstants::STREAM))->firstWhere('descripcion', 'Dos'));
    }

    public function test_si_redis_sigue_caido_no_recupera_y_lo_informa(): void
    {
        $this->actingAsSuperAdmin();
        $this->eventoEnRespaldo();
        $this->redis->caido = true;

        $this->postJson(self::URL.'/recuperar', ['todos' => true])->assertOk()->assertExactJson(['recuperados' => 0, 'fallidos' => 1]);

        $this->redis->caido = false;
        $this->assertSame([], $this->redis->payloads(AuditoriaConstants::STREAM));
    }

    public function test_valida_la_peticion_de_recuperacion(): void
    {
        $this->actingAsSuperAdmin();

        $this->postJson(self::URL.'/recuperar', [])->assertStatus(422)->assertJsonValidationErrors('evento_ids');
        $this->postJson(self::URL.'/recuperar', ['evento_ids' => ['no-es-uuid']])->assertStatus(422)->assertJsonValidationErrors('evento_ids.0');
    }

    public function test_todos_en_falso_sin_ids_no_recupera_nada(): void
    {
        $this->actingAsSuperAdmin();
        $this->eventoEnRespaldo();

        $this->postJson(self::URL.'/recuperar', ['todos' => false, 'evento_ids' => []])->assertStatus(422);
        $this->assertSame([], $this->redis->payloads(AuditoriaConstants::STREAM));
    }
}
