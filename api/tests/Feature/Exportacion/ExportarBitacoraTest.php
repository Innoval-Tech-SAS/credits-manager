<?php

namespace Tests\Feature\Exportacion;

use App\Auditoria\Constants\AuditoriaConstants;
use App\Auditoria\Enums\AccionAuditoria;
use App\Auditoria\Repositories\BitacoraAuditoriaRepository;
use App\Exportacion\Constants\ExportacionConstants;
use App\Exportacion\Enums\EstadoExportacion;
use App\Exportacion\Jobs\ProcesarExportacionJob;
use App\Exportacion\Models\Exportacion;
use App\Exportacion\Services\ExportacionService;
use App\Notificaciones\Events\NotificacionCreada;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Reader\XLSX\Reader;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesAuditoriaFixtures;
use Tests\TestCase;

class ExportarBitacoraTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesAuditoriaFixtures;
    use RefreshDatabase;

    private const URL = '/api/v1/auditoria/bitacora/exportar';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(ExportacionConstants::DISCO);
    }

    private function ejecutarJobEncolado(): void
    {
        Queue::assertPushed(ProcesarExportacionJob::class);

        Queue::pushed(ProcesarExportacionJob::class)->last()->handle(app(ExportacionService::class));
    }

    private function rutaDelArchivo(Exportacion $exportacion): string
    {
        return Storage::disk(ExportacionConstants::DISCO)->path($exportacion->fresh()->ruta_archivo);
    }

    /** @return list<list<mixed>> filas de la hoja "Datos" */
    private function leerDatosXlsx(string $ruta): array
    {
        $lector = new Reader;
        $lector->open($ruta);
        $filas = [];

        foreach ($lector->getSheetIterator() as $hoja) {
            if ($hoja->getName() !== 'Datos') {
                continue;
            }

            foreach ($hoja->getRowIterator() as $fila) {
                $filas[] = $fila->toArray();
            }
        }

        $lector->close();

        return $filas;
    }

    private function idsDeEvento(array $filasXlsx): array
    {
        return collect(array_slice($filasXlsx, 1))->pluck(15)->sort()->values()->all();
    }

    public function test_sin_autenticar_recibe_401(): void
    {
        $this->postJson(self::URL, ['formato' => 'xlsx'])->assertStatus(401);
    }

    public function test_un_rol_operativo_recibe_403_y_no_se_encola_nada(): void
    {
        $this->actingAsAsesor();

        $this->postJson(self::URL, ['formato' => 'xlsx'])->assertStatus(403);

        Queue::assertNothingPushed();
        $this->assertSame(0, Exportacion::query()->count());
    }

    public function test_listar_no_implica_poder_exportar(): void
    {
        $this->actingAsRolConPermisos('auditoria', ['auditoria.listar']);

        $this->getJson('/api/v1/auditoria/bitacora')->assertOk();
        $this->postJson(self::URL, ['formato' => 'xlsx'])->assertStatus(403);
    }

    public function test_el_permiso_exportar_basta_para_exportar(): void
    {
        $this->actingAsRolConPermisos('auditoria', ['auditoria.exportar_bitacora']);

        $this->postJson(self::URL, ['formato' => 'csv'])->assertStatus(202);
    }

    public function test_admin_y_superadmin_exportan_sin_tener_el_permiso_asignado(): void
    {
        $this->actingAsAdmin();
        $this->postJson(self::URL, ['formato' => 'xlsx'])->assertStatus(202);

        $this->actingAsSuperAdmin();
        $this->postJson(self::URL, ['formato' => 'xlsx'])->assertStatus(202);
    }

    public function test_valida_el_formato_y_los_filtros(): void
    {
        $this->actingAsAdmin();

        $this->postJson(self::URL, [])->assertStatus(422)->assertJsonValidationErrors('formato');
        $this->postJson(self::URL, ['formato' => 'pdf'])->assertStatus(422)->assertJsonValidationErrors('formato');
        $this->postJson(self::URL, ['formato' => 'xlsx', 'accion' => 'inventada', 'desde' => '2026-05-02', 'hasta' => '2026-05-01'])
            ->assertStatus(422)->assertJsonValidationErrors(['accion', 'hasta']);
    }

    public function test_acepta_con_202_registra_la_solicitud_encola_y_audita_con_los_filtros(): void
    {
        $admin = $this->actingAsAdmin();
        $this->crearEventoBitacora(['empresa_id' => $admin->empresa_id, 'modulo' => 'creditos']);
        $this->crearEventoBitacora(['empresa_id' => $admin->empresa_id, 'modulo' => 'clientes']);

        $respuesta = $this->postJson(self::URL, ['formato' => 'xlsx', 'modulo' => 'creditos'])->assertStatus(202);

        $respuesta->assertJsonPath('exportacion.estado', 'pendiente')
            ->assertJsonPath('exportacion.formato', 'xlsx')
            ->assertJsonPath('exportacion.filas', 1)
            ->assertJsonMissingPath('exportacion.ruta_archivo');

        $this->assertDatabaseHas('exportaciones', [
            'id' => $respuesta->json('exportacion.id'), 'empresa_id' => $admin->empresa_id, 'usuario_id' => $admin->id,
            'reporte' => 'auditoria.bitacora', 'estado' => 'pendiente',
        ]);

        Queue::assertPushed(ProcesarExportacionJob::class, fn (ProcesarExportacionJob $job) => $job->connection === 'redis' && $job->queue === 'exportaciones' && $job->empresaId === $admin->empresa_id);

        $evento = $this->redis->payloads(AuditoriaConstants::STREAM)[0];

        $this->assertSame('exportar', $evento['accion']);
        $this->assertSame('auditoria', $evento['modulo']);
        $this->assertSame($admin->id, $evento['autor']['usuario_id']);
        $this->assertSame($admin->empresa_id, $evento['empresa_id']);
        $this->assertSame(['reporte' => 'auditoria.bitacora', 'formato' => 'xlsx', 'filtros' => ['modulo' => 'creditos'], 'filas' => 1], $evento['valores_nuevos']);
    }

    public function test_rechaza_con_422_lo_que_supera_el_limite_de_excel_y_acepta_el_limite_exacto(): void
    {
        $this->actingAsAdmin();

        $this->partialMock(BitacoraAuditoriaRepository::class, fn ($mock) => $mock->shouldReceive('contarVisibles')->once()->andReturn(ExportacionConstants::FILAS_MAXIMAS + 1));

        $this->postJson(self::URL, ['formato' => 'xlsx'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('formato')
            ->assertJsonPath('errors.formato.0', 'La exportación tiene 1.048.576 filas y el máximo es 1.048.575 (límite de Excel). Acota los filtros e inténtalo de nuevo.');

        Queue::assertNothingPushed();
        $this->assertSame(0, Exportacion::query()->count());
    }

    public function test_el_limite_exacto_se_acepta(): void
    {
        $this->actingAsAdmin();

        $this->partialMock(BitacoraAuditoriaRepository::class, fn ($mock) => $mock->shouldReceive('contarVisibles')->once()->andReturn(ExportacionConstants::FILAS_MAXIMAS));

        $this->postJson(self::URL, ['formato' => 'csv'])->assertStatus(202);
    }

    public function test_el_xlsx_generado_tiene_las_mismas_filas_y_filtros_que_el_listado(): void
    {
        $admin = $this->actingAsAdmin();
        $otra = $this->crearEmpresaDePrueba();

        foreach (range(1, 7) as $i) {
            $this->crearEventoBitacora(['empresa_id' => $admin->empresa_id, 'modulo' => $i % 2 ? 'creditos' : 'clientes', 'accion' => AccionAuditoria::Aprobar, 'fecha_hora' => "2026-03-{$i} 10:00:00"]);
        }
        $this->crearEventoBitacora(['empresa_id' => $admin->empresa_id, 'modulo' => 'creditos', 'accion' => AccionAuditoria::Aprobar, 'fecha_hora' => '2026-04-20 10:00:00']);
        $this->crearEventoBitacora(['empresa_id' => $otra->id, 'modulo' => 'creditos', 'accion' => AccionAuditoria::Aprobar, 'fecha_hora' => '2026-03-03 10:00:00']);
        $this->crearEventoBitacora(['empresa_id' => null, 'es_superadmin' => true, 'modulo' => 'creditos', 'fecha_hora' => '2026-03-03 10:00:00']);

        $filtros = ['modulo' => 'creditos', 'accion' => 'aprobar', 'desde' => '2026-03-01', 'hasta' => '2026-03-31'];

        $enPantalla = collect($this->getJson('/api/v1/auditoria/bitacora?'.http_build_query($filtros + ['per_page' => 100]))->assertOk()->json('data'))
            ->pluck('evento_id')->sort()->values()->all();

        $id = $this->postJson(self::URL, ['formato' => 'xlsx'] + $filtros)->assertStatus(202)->json('exportacion.id');
        $this->ejecutarJobEncolado();

        $exportacion = Exportacion::query()->findOrFail($id);
        $filas = $this->leerDatosXlsx($this->rutaDelArchivo($exportacion));

        $this->assertSame($enPantalla, $this->idsDeEvento($filas));
        $this->assertCount(4, $enPantalla);
        $this->assertSame(EstadoExportacion::Completada, $exportacion->estado);
        $this->assertSame(4, $exportacion->filas);
        $this->assertGreaterThan(0, $exportacion->tamano_bytes);
        $this->assertMatchesRegularExpression('/^bitacora-auditoria-\d{8}-\d{6}\.xlsx$/', $exportacion->nombre_archivo);
        $this->assertSame('Fecha y hora', $filas[0][0]);
    }

    public function test_la_exportacion_es_una_foto_del_instante_de_la_solicitud(): void
    {
        $admin = $this->actingAsAdmin();
        $antes = $this->crearEventoBitacora(['empresa_id' => $admin->empresa_id]);

        $id = $this->postJson(self::URL, ['formato' => 'xlsx'])->assertStatus(202)->json('exportacion.id');

        // Llega a la bitácora mientras el job espera en la cola (p. ej. el propio evento de la exportación).
        $this->crearEventoBitacora(['empresa_id' => $admin->empresa_id, 'descripcion' => 'Llegó después de la solicitud']);

        $this->ejecutarJobEncolado();

        $exportacion = Exportacion::query()->findOrFail($id);
        $filas = $this->leerDatosXlsx($this->rutaDelArchivo($exportacion));

        $this->assertSame([$antes->evento_id], $this->idsDeEvento($filas));
        $this->assertSame(1, $exportacion->filas, 'Lo validado al aceptar (filas y tope) es lo que se escribe.');
    }

    public function test_un_admin_nunca_exporta_eventos_de_otra_empresa_ni_aunque_envie_su_empresa_id(): void
    {
        $admin = $this->actingAsAdmin();
        $otra = $this->crearEmpresaDePrueba();
        $propio = $this->crearEventoBitacora(['empresa_id' => $admin->empresa_id]);
        $this->crearEventoBitacora(['empresa_id' => $otra->id]);

        $id = $this->postJson(self::URL, ['formato' => 'xlsx', 'empresa_id' => $otra->id])->assertStatus(202)->json('exportacion.id');
        $this->ejecutarJobEncolado();

        $filas = $this->leerDatosXlsx($this->rutaDelArchivo(Exportacion::query()->findOrFail($id)));

        $this->assertSame([$propio->evento_id], $this->idsDeEvento($filas));
        $this->assertNull(Exportacion::query()->findOrFail($id)->filtros['empresa_id'] ?? null, 'El filtro de empresa ajeno ni siquiera queda registrado.');
    }

    public function test_el_superadmin_exporta_todas_las_empresas_y_puede_filtrar_por_una(): void
    {
        $this->actingAsSuperAdmin();
        $a = $this->crearEmpresaDePrueba();
        $b = $this->crearEmpresaDePrueba();
        $deA = $this->crearEventoBitacora(['empresa_id' => $a->id]);
        $deB = $this->crearEventoBitacora(['empresa_id' => $b->id]);
        $suyo = $this->crearEventoBitacora(['empresa_id' => null, 'es_superadmin' => true]);

        $todas = $this->postJson(self::URL, ['formato' => 'xlsx'])->assertStatus(202)->json('exportacion.id');
        $this->ejecutarJobEncolado();
        $solo = $this->postJson(self::URL, ['formato' => 'xlsx', 'empresa_id' => $a->id])->assertStatus(202)->json('exportacion.id');
        $this->ejecutarJobEncolado();

        $ids = [$deA->evento_id, $deB->evento_id, $suyo->evento_id];
        sort($ids);

        $this->assertNull(Exportacion::query()->findOrFail($todas)->empresa_id);
        $this->assertSame($ids, $this->idsDeEvento($this->leerDatosXlsx($this->rutaDelArchivo(Exportacion::query()->findOrFail($todas)))));
        $this->assertSame([$deA->evento_id], $this->idsDeEvento($this->leerDatosXlsx($this->rutaDelArchivo(Exportacion::query()->findOrFail($solo)))));
    }

    public function test_el_csv_lleva_bom_las_mismas_filas_y_neutraliza_formulas_de_usuarios(): void
    {
        $admin = $this->actingAsAdmin();
        $this->crearEventoBitacora(['empresa_id' => $admin->empresa_id, 'descripcion' => '=HYPERLINK("http://malo","clic")', 'autor_nombre' => '+cmd|calc']);
        $this->crearEventoBitacora(['empresa_id' => $admin->empresa_id, 'descripcion' => 'Normal']);

        $id = $this->postJson(self::URL, ['formato' => 'csv'])->assertStatus(202)->json('exportacion.id');
        $this->ejecutarJobEncolado();

        $contenido = file_get_contents($this->rutaDelArchivo(Exportacion::query()->findOrFail($id)));

        $this->assertStringStartsWith("\xEF\xBB\xBF", $contenido);
        $this->assertStringContainsString("'=HYPERLINK", $contenido);
        $this->assertStringNotContainsString(',=HYPERLINK', $contenido);
        $this->assertStringContainsString("'+cmd|calc", $contenido);
        $this->assertCount(3, array_filter(explode("\n", trim(substr($contenido, 3)))));
    }

    public function test_los_valores_anteriores_y_nuevos_salen_como_json_en_el_archivo(): void
    {
        $admin = $this->actingAsAdmin();
        $this->crearEventoBitacora(['empresa_id' => $admin->empresa_id, 'valores_anteriores' => ['nombre' => 'A'], 'valores_nuevos' => ['nombre' => 'B']]);

        $id = $this->postJson(self::URL, ['formato' => 'xlsx'])->assertStatus(202)->json('exportacion.id');
        $this->ejecutarJobEncolado();

        $fila = $this->leerDatosXlsx($this->rutaDelArchivo(Exportacion::query()->findOrFail($id)))[1];

        $this->assertSame('{"nombre":"A"}', $fila[12]);
        $this->assertSame('{"nombre":"B"}', $fila[13]);
        $this->assertInstanceOf(\DateTimeInterface::class, $fila[0]);
        $this->assertFalse($fila[5]);
    }

    public function test_al_terminar_notifica_al_solicitante_por_persistencia_y_por_websocket(): void
    {
        Event::fake([NotificacionCreada::class]);
        $admin = $this->actingAsAdmin();
        $this->crearEventoBitacora(['empresa_id' => $admin->empresa_id]);

        $id = $this->postJson(self::URL, ['formato' => 'xlsx'])->assertStatus(202)->json('exportacion.id');
        $this->ejecutarJobEncolado();

        $this->assertDatabaseHas('notificaciones', [
            'usuario_id' => $admin->id, 'empresa_id' => $admin->empresa_id, 'tipo' => 'exportacion_lista', 'titulo' => 'Exportación lista', 'leida_en' => null,
        ]);

        Event::assertDispatched(NotificacionCreada::class, fn (NotificacionCreada $evento) => $evento->usuarioId === $admin->id && $evento->notificacion['datos']['exportacion_id'] === $id);
    }

    public function test_el_worker_no_depende_de_la_sesion(): void
    {
        $admin = $this->actingAsAdmin();
        $propio = $this->crearEventoBitacora(['empresa_id' => $admin->empresa_id]);
        $this->crearEventoBitacora(['empresa_id' => $this->crearEmpresaDePrueba()->id]);

        $id = $this->postJson(self::URL, ['formato' => 'xlsx'])->assertStatus(202)->json('exportacion.id');

        // El worker corre sin usuario autenticado.
        $this->salirDeSesion();
        $this->ejecutarJobEncolado();

        $exportacion = Exportacion::query()->withoutGlobalScopes()->findOrFail($id);

        $this->assertSame(EstadoExportacion::Completada, $exportacion->estado);
        $this->assertSame([$propio->evento_id], $this->idsDeEvento($this->leerDatosXlsx($this->rutaDelArchivo($exportacion))));
    }

    public function test_la_hoja_de_parametros_registra_quien_cuando_y_con_que_filtros(): void
    {
        $admin = $this->actingAsAdmin();

        $id = $this->postJson(self::URL, ['formato' => 'xlsx', 'modulo' => 'creditos', 'autor' => 'ana'])->assertStatus(202)->json('exportacion.id');
        $this->ejecutarJobEncolado();

        $lector = new Reader;
        $lector->open($this->rutaDelArchivo(Exportacion::query()->findOrFail($id)));
        $parametros = [];

        foreach ($lector->getSheetIterator() as $hoja) {
            if ($hoja->getName() === 'Parámetros') {
                foreach ($hoja->getRowIterator() as $fila) {
                    $parametros[trim($fila->toArray()[0])] = $fila->toArray()[1] ?? null;
                }
            }
        }

        $this->assertSame('Bitácora de auditoría', $parametros['Reporte']);
        $this->assertSame($admin->nombre, $parametros['Generado por']);
        $this->assertSame('Empresa de prueba', substr($parametros['Empresa'], 0, 17));
        $this->assertSame('creditos', $parametros['modulo']);
        $this->assertSame('ana', $parametros['autor']);
    }
}
