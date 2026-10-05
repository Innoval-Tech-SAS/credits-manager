<?php

namespace Tests\Feature\Importacion;

use App\Importacion\Enums\EstadoImportacion;
use App\Importacion\Models\Importacion;
use App\Importacion\Services\ImportacionEjecutorService;
use App\Usuarios\Models\Permiso;
use App\Usuarios\Models\Usuario;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\AfirmaAuditoria;
use Tests\Concerns\ImportaArchivos;
use Tests\TestCase;

class ImportacionAccesoTest extends TestCase
{
    use ActsAsAdmin;
    use AfirmaAuditoria;
    use ImportaArchivos;
    use RefreshDatabase;

    private const ENCABEZADO_CLIENTES = "Cédula;Nombre completo\n";

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararImportaciones();
    }

    private function concederPermiso(Usuario $usuario, string $modulo, string $accion): void
    {
        $usuario->permisosExtra()->attach(Permiso::query()->where(['modulo' => $modulo, 'accion' => $accion])->value('id'), ['concedido' => true]);
    }

    public function test_sin_sesion_responde_401(): void
    {
        $this->getJson('/api/v1/importaciones')->assertUnauthorized();
        $this->postJson('/api/v1/importaciones', [])->assertUnauthorized();
    }

    public function test_un_asesor_sin_permisos_de_importacion_recibe_403(): void
    {
        $this->actingAsAsesor();

        $this->getJson('/api/v1/importaciones')->assertForbidden();
        $this->postJson('/api/v1/importaciones', [
            'tipo' => 'clientes',
            'archivo' => UploadedFile::fake()->createWithContent('clientes.csv', self::ENCABEZADO_CLIENTES),
        ])->assertForbidden();
    }

    public function test_subir_una_hoja_exige_el_permiso_de_crear_de_su_modulo_destino(): void
    {
        $asesor = $this->actingAsAsesor();
        $this->concederPermiso($asesor, 'importaciones', 'ejecutar');

        $this->postJson('/api/v1/importaciones', [
            'tipo' => 'creditos',
            'archivo' => UploadedFile::fake()->createWithContent('creditos.csv', "Código de crédito;Cédula cliente;Fecha de desembolso;Valor desembolsado (capital);Tasa de interés mensual (%);Plazo (# cuotas);Periodicidad (Mensual/Quincenal)\n"),
        ])->assertForbidden();

        $this->assertDatabaseCount('importaciones', 0);
    }

    public function test_la_importacion_de_otra_empresa_responde_404_en_todas_sus_acciones(): void
    {
        $this->actingAsAdmin();
        $id = $this->importarCompleto('clientes', self::ENCABEZADO_CLIENTES."1001;Ana Pérez\n");

        $this->actingAsAdmin();

        $this->getJson("/api/v1/importaciones/{$id}")->assertNotFound();
        $this->getJson("/api/v1/importaciones/{$id}/errores")->assertNotFound();
        $this->getJson("/api/v1/importaciones/{$id}/reporte")->assertNotFound();
        $this->postJson("/api/v1/importaciones/{$id}/decisiones", ['crear_empleadores' => true])->assertNotFound();
        $this->postJson("/api/v1/importaciones/{$id}/confirmar")->assertNotFound();
        $this->postJson("/api/v1/importaciones/{$id}/cancelar")->assertNotFound();
    }

    public function test_el_listado_muestra_solo_las_importaciones_de_la_empresa(): void
    {
        $this->actingAsAdmin();
        $this->importarCompleto('clientes', self::ENCABEZADO_CLIENTES."1001;Ana Pérez\n");

        $this->actingAsAdmin();
        $this->importarCompleto('clientes', self::ENCABEZADO_CLIENTES."2002;Luis Ramírez\n");

        $this->getJson('/api/v1/importaciones')->assertOk()->assertJsonPath('total', 1);
    }

    public function test_el_worker_no_valida_una_importacion_con_otra_empresa(): void
    {
        $this->actingAsAdmin();
        $id = $this->subirArchivo('clientes', self::ENCABEZADO_CLIENTES."1001;Ana Pérez\n");
        $empresaAjena = $this->importacion($id)->empresa_id + 999;

        $this->expectException(ModelNotFoundException::class);
        app(ImportacionEjecutorService::class)->validar($id, $empresaAjena);
    }

    public function test_un_tipo_desconocido_o_un_archivo_que_no_es_csv_responde_422(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/importaciones', [
            'tipo' => 'facturas',
            'archivo' => UploadedFile::fake()->createWithContent('x.csv', 'a;b'),
        ])->assertUnprocessable()->assertJsonValidationErrors('tipo');

        $this->postJson('/api/v1/importaciones', [
            'tipo' => 'clientes',
            'archivo' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'),
        ])->assertUnprocessable()->assertJsonValidationErrors('archivo');
    }

    public function test_generar_asiento_en_creditos_exige_la_cuenta_de_contrapartida(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/importaciones', [
            'tipo' => 'creditos',
            'generar_asiento' => true,
            'archivo' => UploadedFile::fake()->createWithContent('creditos.csv', "Código de crédito;Cédula cliente;Fecha de desembolso;Valor desembolsado (capital);Tasa de interés mensual (%);Plazo (# cuotas);Periodicidad (Mensual/Quincenal)\n"),
        ])->assertUnprocessable()->assertJsonValidationErrors('cuenta_contrapartida_id');
    }

    public function test_cancelar_antes_de_confirmar_y_luego_no_se_puede_confirmar(): void
    {
        $this->actingAsAdmin();
        $id = $this->subirArchivo('clientes', self::ENCABEZADO_CLIENTES."1001;Ana Pérez\n");
        $this->ejecutarValidacion($id);

        $this->postJson("/api/v1/importaciones/{$id}/cancelar")->assertOk()
            ->assertJsonPath('importacion.estado', 'cancelada');
        $this->postJson("/api/v1/importaciones/{$id}/confirmar")->assertUnprocessable()->assertJsonValidationErrors('estado');

        $this->assertSame(EstadoImportacion::Cancelada, $this->importacion($id)->estado);
        $this->assertDatabaseCount('clientes', 0);
        $this->assertEventoAuditoria('cancelar', 'importaciones', 'importacion', $id);
    }

    public function test_las_decisiones_solo_se_registran_en_estado_pendiente_de_decision(): void
    {
        $this->actingAsAdmin();
        $id = $this->subirArchivo('clientes', self::ENCABEZADO_CLIENTES."1001;Ana Pérez\n");

        $this->postJson("/api/v1/importaciones/{$id}/decisiones", ['crear_empleadores' => true])
            ->assertUnprocessable()->assertJsonValidationErrors('estado');
    }

    public function test_el_reporte_de_errores_se_descarga_en_csv_y_queda_en_la_bitacora(): void
    {
        $this->actingAsAdmin();
        $id = $this->importarCompleto('clientes', self::ENCABEZADO_CLIENTES."1003;Carlos\n");

        $respuesta = $this->get("/api/v1/importaciones/{$id}/reporte")->assertOk();

        $this->assertStringContainsString('text/csv', $respuesta->headers->get('content-type'));
        $this->assertStringContainsString('nombres y apellidos', $respuesta->streamedContent());
        $this->assertEventoAuditoria('exportar', 'importaciones', 'importacion', $id);
    }

    public function test_la_guia_de_una_hoja_incluye_sus_columnas_y_la_fila_de_encabezados(): void
    {
        $this->actingAsAdmin();

        $contenido = $this->get('/api/v1/importaciones/guias/clientes')->assertOk()->streamedContent();

        $this->assertStringContainsString('Cédula', $contenido);
        $this->assertStringContainsString('Nombre completo', $contenido);
    }

    public function test_una_importacion_fallida_avisa_y_no_deja_el_archivo_en_estado_intermedio(): void
    {
        $this->actingAsAdmin();
        $id = $this->subirArchivo('clientes', self::ENCABEZADO_CLIENTES."1001;Ana Pérez\n");

        app(ImportacionEjecutorService::class)->marcarFallida($id, $this->importacion($id)->empresa_id, new \RuntimeException('timeout'));

        $this->assertSame(EstadoImportacion::Fallida, $this->importacion($id)->estado);
        $this->assertDatabaseHas('notificaciones', ['tipo' => 'importacion_fallida']);
    }

    public function test_la_limpieza_borra_el_csv_y_conserva_el_registro(): void
    {
        $this->actingAsAdmin();
        $id = $this->importarCompleto('clientes', self::ENCABEZADO_CLIENTES."1001;Ana Pérez\n");
        $ruta = $this->importacion($id)->getRawOriginal('archivo_ruta');
        Importacion::query()->whereKey($id)->update(['creado_en' => now()->subDays(10)]);

        $this->artisan('importaciones:limpiar', ['--dias' => 7])->assertSuccessful();

        Storage::disk('importaciones')->assertMissing($ruta);
        $this->assertNull($this->importacion($id)->getRawOriginal('archivo_ruta'));
        $this->assertDatabaseHas('importaciones', ['id' => $id]);
    }
}
