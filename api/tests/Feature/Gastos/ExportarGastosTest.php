<?php

namespace Tests\Feature\Gastos;

use App\Exportacion\Constants\ExportacionConstants;
use App\Exportacion\Jobs\ProcesarExportacionJob;
use App\Exportacion\Models\Exportacion;
use App\Exportacion\Services\ExportacionService;
use App\Gastos\Enums\EstadoGasto;
use App\Gastos\Models\Gasto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Reader\XLSX\Reader;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesAuditoriaFixtures;
use Tests\Concerns\CreatesContabilidadFixtures;
use Tests\Concerns\CreatesGastosFixtures;
use Tests\TestCase;

class ExportarGastosTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesAuditoriaFixtures;
    use CreatesContabilidadFixtures;
    use CreatesGastosFixtures;
    use RefreshDatabase;

    private const EXPORTAR = '/api/v1/gastos/exportar';

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

    /** Un gasto causado de $neto con el concepto dado, en la sesión actual. */
    private function gastoDe(string $concepto, int $neto): Gasto
    {
        $tipoGasto = $this->crearTipoGasto();

        return Gasto::create([
            'tipo_gasto_id' => $tipoGasto->id,
            'parametrizacion_tipo_gasto_id' => $this->crearParametrizacion($tipoGasto)->id,
            'fecha' => '2026-02-10',
            'numero_documento' => 'FE-'.$concepto,
            'concepto' => $concepto,
            'valor_bruto' => $neto,
            'valor_iva' => 0,
            'valor_retefuente' => 0,
            'valor_reteica' => 0,
            'valor_reteiva' => 0,
            'valor_neto' => $neto,
            'estado' => EstadoGasto::Causado,
            'ajuste_manual' => false,
        ]);
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

    public function test_valida_el_formato(): void
    {
        $this->actingAsAdmin();

        $this->postJson(self::EXPORTAR, [])->assertStatus(422)->assertJsonValidationErrors('formato');
        $this->postJson(self::EXPORTAR, ['formato' => 'pdf'])->assertStatus(422)->assertJsonValidationErrors('formato');

        Queue::assertNothingPushed();
    }

    public function test_exporta_los_gastos_con_su_total(): void
    {
        $this->actingAsAdmin();
        $this->gastoDe('Papelería', 150);
        $this->gastoDe('Internet', 50);

        $respuesta = $this->postJson(self::EXPORTAR, ['formato' => 'xlsx'])->assertStatus(202);

        $this->assertDatabaseHas('exportaciones', ['id' => $respuesta->json('exportacion.id'), 'reporte' => 'gastos.listado']);

        $this->ejecutarJobs();

        $datos = $this->leerDatos(Exportacion::find($respuesta->json('exportacion.id')));

        $this->assertCount(4, $datos, 'encabezado, dos gastos y el total');
        $this->assertSame('Total', $datos[3][5]);
        $this->assertEquals(200, $datos[3][11]);
    }

    public function test_la_busqueda_filtra_como_en_la_pantalla(): void
    {
        $this->actingAsAdmin();
        $this->gastoDe('Papelería', 150);
        $this->gastoDe('Internet', 50);

        $respuesta = $this->postJson(self::EXPORTAR, ['formato' => 'xlsx', 'search' => 'Internet'])->assertStatus(202);

        $this->ejecutarJobs();

        $conceptos = array_column(array_slice($this->leerDatos(Exportacion::find($respuesta->json('exportacion.id'))), 1, -1), 5);

        $this->assertSame(['Internet'], $conceptos);
    }

    public function test_el_worker_exporta_solo_la_empresa_que_pidio_la_exportacion(): void
    {
        $this->actingAsAdmin();
        $this->gastoDe('Gasto de la empresa A', 100);

        $this->actingAsAdmin();
        $this->gastoDe('Gasto de la empresa B', 900);

        $respuesta = $this->postJson(self::EXPORTAR, ['formato' => 'xlsx'])->assertStatus(202);

        $this->salirDeSesion();
        $this->ejecutarJobs();

        $datos = $this->leerDatos(Exportacion::find($respuesta->json('exportacion.id')));

        $this->assertSame('Gasto de la empresa B', $datos[1][5]);
        $this->assertEquals(900, $datos[2][11], 'el total no incluye el gasto de la otra empresa');
    }
}
