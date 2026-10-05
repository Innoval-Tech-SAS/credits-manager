<?php

namespace Tests\Feature\Cartera;

use App\Creditos\Enums\EstadoCuota;
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

class ExportarCarteraTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesAuditoriaFixtures;
    use CreatesCreditFixtures;
    use RefreshDatabase;

    private const EXPORTAR = '/api/v1/cartera/exportar';

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

    /** Un cliente con un crédito al día y otro en mora, en la sesión actual. */
    private function carteraConAlDiaYEnMora(): void
    {
        $ana = $this->crearCliente(['nombre' => 'Ana Gomez']);
        $creditoAna = $this->crearCredito($ana);
        $this->crearCuota($creditoAna, ['numero_cuota' => 1, 'fecha_vencimiento' => now()->addMonth()->toDateString(), 'valor' => 300]);

        $luis = $this->crearCliente(['nombre' => 'Luis Perez']);
        $creditoLuis = $this->crearCredito($luis);
        $this->crearCuota($creditoLuis, ['numero_cuota' => 1, 'fecha_vencimiento' => '2026-01-10', 'valor' => 500, 'estado' => EstadoCuota::Vencida]);
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

    public function test_exporta_la_cartera_con_su_estado_de_mora_y_el_total(): void
    {
        $this->actingAsAdmin();
        $this->carteraConAlDiaYEnMora();

        $respuesta = $this->postJson(self::EXPORTAR, ['formato' => 'xlsx'])->assertStatus(202);

        $this->assertDatabaseHas('exportaciones', ['id' => $respuesta->json('exportacion.id'), 'reporte' => 'cartera.listado']);

        $this->ejecutarJobs();

        $datos = $this->leerDatos(Exportacion::find($respuesta->json('exportacion.id')));

        $this->assertCount(4, $datos, 'encabezado, dos créditos y el total');
        $this->assertContains('En mora', array_column($datos, 2));
        $this->assertContains('Al día', array_column($datos, 2));
        $this->assertSame('Total', $datos[3][1]);
        $this->assertEquals(800, $datos[3][4]);
    }

    public function test_el_asesor_con_alcance_solo_exporta_sus_clientes(): void
    {
        $admin = $this->actingAsAdmin();
        $empresa = Empresa::find($admin->empresa_id);
        $this->crearCredito($this->crearCliente(['nombre' => 'Cliente del admin']));

        $this->actingAsRolConPermisos('asesor', ['cartera.exportar'], $empresa);
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
