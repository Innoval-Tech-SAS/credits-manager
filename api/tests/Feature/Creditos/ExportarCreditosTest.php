<?php

namespace Tests\Feature\Creditos;

use App\Creditos\Enums\EstadoCredito;
use App\Empresas\Models\Empresa;
use App\Exportacion\Constants\ExportacionConstants;
use App\Exportacion\Jobs\ProcesarExportacionJob;
use App\Exportacion\Models\Exportacion;
use App\Exportacion\Services\ExportacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Reader\XLSX\Reader;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesAuditoriaFixtures;
use Tests\Concerns\CreatesCreditFixtures;
use Tests\TestCase;

class ExportarCreditosTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesAuditoriaFixtures;
    use CreatesCreditFixtures;
    use RefreshDatabase;

    private const EXPORTAR = '/api/v1/creditos/exportar';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(ExportacionConstants::DISCO);
        Queue::fake();
    }

    /** Ejecuta, una sola vez cada uno, los jobs encolados hasta ahora, como lo haría el worker. */
    private function ejecutarJobs(): void
    {
        Queue::assertPushed(ProcesarExportacionJob::class);

        Queue::pushed(ProcesarExportacionJob::class)->each(fn (ProcesarExportacionJob $job) => $job->handle(app(ExportacionService::class)));
    }

    /** @return list<list<mixed>> */
    private function leerDatos(Exportacion $exportacion): array
    {
        $lector = new Reader;
        $lector->open(Storage::disk(ExportacionConstants::DISCO)->path($exportacion->fresh()->ruta_archivo));
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

    public function test_sin_autenticar_recibe_401(): void
    {
        $this->postJson(self::EXPORTAR, ['formato' => 'xlsx'])->assertStatus(401);

        Queue::assertNothingPushed();
    }

    public function test_asesor_sin_permiso_de_exportar_recibe_403(): void
    {
        $this->actingAsAsesor();

        $this->postJson(self::EXPORTAR, ['formato' => 'xlsx'])->assertStatus(403);

        Queue::assertNothingPushed();
    }

    public function test_valida_el_formato_y_el_estado(): void
    {
        $this->actingAsAdmin();

        $this->postJson(self::EXPORTAR, [])->assertStatus(422)->assertJsonValidationErrors('formato');
        $this->postJson(self::EXPORTAR, ['formato' => 'xlsx', 'estado' => 'inventado'])->assertStatus(422)->assertJsonValidationErrors('estado');

        Queue::assertNothingPushed();
    }

    public function test_exporta_los_creditos_y_respeta_el_filtro_de_estado(): void
    {
        $this->actingAsAdmin();
        $ana = $this->crearCliente(['nombre' => 'Ana Gomez']);
        $this->crearCredito($ana);
        $this->crearCredito($this->crearCliente(['nombre' => 'Luis Perez']), ['estado' => EstadoCredito::Pagado]);

        $respuesta = $this->postJson(self::EXPORTAR, ['formato' => 'xlsx', 'estado' => 'desembolsado'])->assertStatus(202);

        $this->assertDatabaseHas('exportaciones', ['id' => $respuesta->json('exportacion.id'), 'reporte' => 'creditos.listado']);

        $this->ejecutarJobs();

        $datos = $this->leerDatos(Exportacion::find($respuesta->json('exportacion.id')));

        $this->assertCount(2, $datos, 'encabezado y un solo crédito desembolsado');
        $this->assertSame('Ana Gomez', $datos[1][1]);
        $this->assertSame('desembolsado', $datos[1][3]);
    }

    public function test_el_asesor_con_alcance_solo_exporta_sus_creditos(): void
    {
        $admin = $this->actingAsAdmin();
        $empresa = Empresa::find($admin->empresa_id);
        $this->crearCredito($this->crearCliente(['nombre' => 'Cliente del admin']));

        $this->actingAsRolConPermisos('asesor', ['creditos.exportar_listado'], $empresa);
        $this->crearCredito($this->crearCliente(['nombre' => 'Cliente del asesor']));

        $respuesta = $this->postJson(self::EXPORTAR, ['formato' => 'xlsx'])->assertStatus(202);

        $this->ejecutarJobs();

        $nombres = array_column($this->leerDatos(Exportacion::find($respuesta->json('exportacion.id'))), 1);

        $this->assertContains('Cliente del asesor', $nombres);
        $this->assertNotContains('Cliente del admin', $nombres);
    }

    public function test_el_worker_exporta_solo_la_empresa_que_pidio_la_exportacion(): void
    {
        $this->actingAsAdmin();
        $this->crearCredito($this->crearCliente(['nombre' => 'Cliente de la empresa A']));

        $this->actingAsAdmin();
        $this->crearCredito($this->crearCliente(['nombre' => 'Cliente de la empresa B']));

        $respuesta = $this->postJson(self::EXPORTAR, ['formato' => 'xlsx'])->assertStatus(202);

        $this->salirDeSesion();
        $this->ejecutarJobs();

        $nombres = array_column($this->leerDatos(Exportacion::find($respuesta->json('exportacion.id'))), 1);

        $this->assertContains('Cliente de la empresa B', $nombres);
        $this->assertNotContains('Cliente de la empresa A', $nombres);
    }
}
