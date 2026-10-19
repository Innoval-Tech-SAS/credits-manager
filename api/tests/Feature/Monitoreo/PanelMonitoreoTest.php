<?php

namespace Tests\Feature\Monitoreo;

use App\Auditoria\Enums\AccionAuditoria;
use App\Monitoreo\Constants\MonitoreoConstants;
use App\Monitoreo\Enums\EstadoServicio;
use App\Monitoreo\Services\RegistroLatidos;
use App\Respaldos\Constants\RespaldosConstants;
use Database\Seeders\ConfiguracionRespaldoExternoSeeder;
use Database\Seeders\TareaProgramadaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\AfirmaAuditoria;
use Tests\Concerns\PreparaRespaldos;
use Tests\TestCase;

class PanelMonitoreoTest extends TestCase
{
    use ActsAsAdmin;
    use AfirmaAuditoria;
    use PreparaRespaldos;
    use RefreshDatabase;

    private string $procFalso;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TareaProgramadaSeeder::class);
        $this->seed(ConfiguracionRespaldoExternoSeeder::class);
        $this->prepararRespaldos();
        File::ensureDirectoryExists($this->raizRespaldos);
        $this->procFalso = sys_get_temp_dir().'/proc-test-'.uniqid();
        File::ensureDirectoryExists($this->procFalso);
        file_put_contents($this->procFalso.'/stat', "cpu  100 0 100 800 0 0 0 0 0 0\ncpu0 50 0 50 400 0 0 0 0 0 0\ncpu1 50 0 50 400 0 0 0 0 0 0\n");
        file_put_contents($this->procFalso.'/meminfo', "MemTotal:        8000000 kB\nMemFree:         1000000 kB\nMemAvailable:    6000000 kB\nSwapTotal:       2000000 kB\nSwapFree:        1500000 kB\n");
        file_put_contents($this->procFalso.'/loadavg', "0.52 0.48 0.40 1/234 5678\n");
        file_put_contents($this->procFalso.'/uptime', "93784.12 180000.00\n");
        File::ensureDirectoryExists($this->procFalso.'/net');
        file_put_contents($this->procFalso.'/net/dev', "Inter-|   Receive                                                |  Transmit\n face |bytes    packets errs drop fifo frame compressed multicast|bytes    packets errs drop fifo colls carrier compressed\n    lo:  1296      16    0    0    0     0          0         0     1296      16    0    0    0     0       0          0\n  eth0: 500000     400    0    0    0     0          0         0   300000     200    0    0    0     0       0          0\n");

        // Puertos cerrados en local: los servicios TCP salen caídos al instante, sin esperar DNS del compose.
        config([
            'monitoreo.ruta_proc' => $this->procFalso,
            'monitoreo.servicios_tcp' => ['api' => ['host' => '127.0.0.1', 'puerto' => 1]],
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->procFalso);
        $this->limpiarRespaldos();

        parent::tearDown();
    }

    public function test_sin_autenticar_responde_401(): void
    {
        $this->getJson('/api/v1/monitoreo/estado')->assertUnauthorized();
        $this->getJson('/api/v1/monitoreo/respaldos')->assertUnauthorized();
        $this->getJson('/api/v1/monitoreo/trabajos-fallidos')->assertUnauthorized();
    }

    public function test_el_administrador_de_una_empresa_no_ve_el_panel(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/v1/monitoreo/estado')->assertForbidden();
        $this->getJson('/api/v1/monitoreo/respaldos')->assertForbidden();
        $this->getJson('/api/v1/monitoreo/trabajos-fallidos')->assertForbidden();
        $this->postJson('/api/v1/monitoreo/trabajos-fallidos/'.Str::uuid().'/reintentar')->assertForbidden();
    }

    public function test_estado_trae_las_metricas_del_servidor_leidas_de_proc(): void
    {
        $this->actingAsSuperAdmin();

        $respuesta = $this->getJson('/api/v1/monitoreo/estado')->assertOk();

        $respuesta->assertJsonPath('servidor.cpu.nucleos', 2)
            ->assertJsonPath('servidor.memoria.total_bytes', 8000000 * 1024)
            ->assertJsonPath('servidor.memoria.usada_bytes', 2000000 * 1024)
            ->assertJsonPath('servidor.memoria.swap_usada_bytes', 500000 * 1024)
            ->assertJsonPath('servidor.carga', [0.52, 0.48, 0.4])
            ->assertJsonPath('servidor.encendido_segundos', 93784)
            ->assertJsonPath('servidor.red.rx_bytes', 500000)
            ->assertJsonPath('servidor.red.tx_bytes', 300000)
            ->assertJsonStructure([
                'servidor' => ['disco' => ['total_bytes', 'usado_bytes', 'porcentaje']],
                'servicios', 'colas', 'auditoria', 'tareas_programadas', 'generado_en',
            ]);

        // JSON no distingue 25.0 de 25: se compara por valor, no por tipo.
        $this->assertEquals(25.0, $respuesta->json('servidor.memoria.porcentaje'));
    }

    public function test_estado_trae_la_ultima_ejecucion_de_cada_tarea_programada(): void
    {
        $this->actingAsSuperAdmin();

        DB::table('ejecuciones_tareas_programadas')->insert([
            ['tarea_clave' => 'metas:evaluar', 'iniciado_en' => now()->subDay(), 'finalizado_en' => now()->subDay(), 'resultado' => 'ok', 'error' => null],
            ['tarea_clave' => 'metas:evaluar', 'iniciado_en' => now(), 'finalizado_en' => now(), 'resultado' => 'error', 'error' => 'Falló'],
        ]);

        $respuesta = $this->getJson('/api/v1/monitoreo/estado')->assertOk();

        $respuesta->assertJsonPath('tareas_programadas.metas:evaluar.resultado', 'error');
    }

    public function test_un_servicio_sin_latido_sale_caido_y_uno_ocupado_muestra_su_trabajo(): void
    {
        $this->actingAsSuperAdmin();
        app(RegistroLatidos::class)->registrar('correos-worker', EstadoServicio::Ocupado, 'App\\Correo\\Jobs\\EnviarRecordatorioCobranzaJob');

        $servicios = collect($this->getJson('/api/v1/monitoreo/estado')->assertOk()->json('servicios'))->keyBy('servicio');

        $this->assertSame('ocupado', $servicios['correos-worker']['estado']);
        $this->assertSame('App\\Correo\\Jobs\\EnviarRecordatorioCobranzaJob', $servicios['correos-worker']['detalle']);
        $this->assertSame('caido', $servicios['exportaciones-worker']['estado']);
        $this->assertSame('caido', $servicios['api']['estado']);
        $this->assertSame('activo', $servicios['database']['estado']);
    }

    public function test_colas_cuentan_los_fallidos_y_la_auditoria_el_atraso_del_stream(): void
    {
        $this->actingAsSuperAdmin();
        $this->trabajoFallido('correos');
        $this->trabajoFallido('correos');

        $colas = collect($this->getJson('/api/v1/monitoreo/estado')->assertOk()->json('colas'))->keyBy('cola');

        $this->assertSame(MonitoreoConstants::COLAS, $colas->keys()->all());
        $this->assertSame(2, $colas['correos']['fallidos']);
        $this->assertSame(0, $colas['exportaciones']['fallidos']);
    }

    public function test_respaldos_lista_por_clase_y_alerta_si_no_hay_diario_reciente(): void
    {
        $this->actingAsSuperAdmin();
        $this->respaldoEnDisco('diario', now()->subHours(30)->format(RespaldosConstants::FORMATO_CARPETA));
        $this->respaldoEnDisco('diario', now()->subHours(54)->format(RespaldosConstants::FORMATO_CARPETA), completo: false);

        $respuesta = $this->getJson('/api/v1/monitoreo/respaldos')->assertOk();

        $respuesta->assertJsonPath('alerta', true)
            ->assertJsonPath('horas_desde_ultimo_diario', 30)
            ->assertJsonPath('clases.0.clase', 'diario')
            ->assertJsonCount(2, 'clases.0.respaldos')
            ->assertJsonPath('clases.0.respaldos.0.completo', true)
            ->assertJsonPath('clases.0.respaldos.1.completo', false)
            ->assertJsonPath('externo.configurado', false);
    }

    public function test_respaldos_sin_alerta_con_un_diario_completo_de_anoche(): void
    {
        $this->actingAsSuperAdmin();
        $this->respaldoEnDisco('diario', now()->subHours(3)->format(RespaldosConstants::FORMATO_CARPETA));

        $this->getJson('/api/v1/monitoreo/respaldos')->assertOk()->assertJsonPath('alerta', false);
    }

    public function test_trabajos_fallidos_muestra_solo_la_primera_linea_del_error_y_filtra_por_cola(): void
    {
        $this->actingAsSuperAdmin();
        $this->trabajoFallido('correos', "RuntimeException: SMTP no responde\n#0 /var/www/html/vendor/traza.php(12)");
        $this->trabajoFallido('exportaciones');

        $respuesta = $this->getJson('/api/v1/monitoreo/trabajos-fallidos?cola=correos')->assertOk();

        $respuesta->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.cola', 'correos')
            ->assertJsonPath('data.0.trabajo', 'EnviarRecordatorioCobranzaJob')
            ->assertJsonPath('data.0.error', 'RuntimeException: SMTP no responde')
            ->assertJsonMissingPath('data.0.payload');
    }

    public function test_trabajos_fallidos_rechaza_una_cola_desconocida(): void
    {
        $this->actingAsSuperAdmin();

        $this->getJson('/api/v1/monitoreo/trabajos-fallidos?cola=inventada')->assertUnprocessable()->assertJsonValidationErrors('cola');
    }

    public function test_reintentar_un_trabajo_inexistente_responde_404(): void
    {
        $this->actingAsSuperAdmin();

        $this->postJson('/api/v1/monitoreo/trabajos-fallidos/'.Str::uuid().'/reintentar')->assertNotFound();
    }

    public function test_reintentar_devuelve_el_trabajo_a_su_cola_y_queda_en_la_bitacora(): void
    {
        $this->actingAsSuperAdmin();
        $uuid = $this->trabajoFallido('correos');

        $this->postJson("/api/v1/monitoreo/trabajos-fallidos/{$uuid}/reintentar")->assertOk();

        $this->assertDatabaseMissing('failed_jobs', ['uuid' => $uuid]);
        $eventos = $this->eventosDeAuditoria(AccionAuditoria::Reintentar->value, MonitoreoConstants::MODULO);
        $this->assertCount(1, $eventos);
        $this->assertNull($eventos[0]['empresa_id']);
    }

    private function trabajoFallido(string $cola, string $error = 'RuntimeException: falló'): string
    {
        $uuid = (string) Str::uuid();

        DB::table('failed_jobs')->insert([
            'uuid' => $uuid,
            'connection' => 'redis',
            'queue' => $cola,
            'payload' => json_encode(['uuid' => $uuid, 'displayName' => 'App\\Correo\\Jobs\\EnviarRecordatorioCobranzaJob', 'job' => 'Illuminate\\Queue\\CallQueuedHandler@call', 'data' => []]),
            'exception' => $error,
            'failed_at' => now(),
        ]);

        return $uuid;
    }

    private function respaldoEnDisco(string $clase, string $carpeta, bool $completo = true): void
    {
        $ruta = $this->raizRespaldos.'/'.$clase.'/'.$carpeta;
        File::ensureDirectoryExists($ruta);

        $archivos = [RespaldosConstants::ARCHIVO_BASE, RespaldosConstants::ARCHIVO_ARCHIVOS, RespaldosConstants::ARCHIVO_SUMAS, RespaldosConstants::ARCHIVO_DATOS];

        foreach ($completo ? $archivos : array_slice($archivos, 0, 1) as $archivo) {
            file_put_contents($ruta.'/'.$archivo, str_repeat('x', 100));
        }
    }
}
