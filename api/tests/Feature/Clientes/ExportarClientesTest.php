<?php

namespace Tests\Feature\Clientes;

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

class ExportarClientesTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesAuditoriaFixtures;
    use CreatesCreditFixtures;
    use RefreshDatabase;

    private const EXPORTAR = '/api/v1/clientes/exportar';

    /** Índice de la columna «Nombre» en el archivo. */
    private const NOMBRE = 3;

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

    /** @return list<mixed> nombres de los clientes exportados (sin la fila de encabezado) */
    private function nombresExportados(Exportacion $exportacion): array
    {
        return array_column(array_slice($this->leerDatos($exportacion), 1), self::NOMBRE);
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

    public function test_valida_el_formato_el_estado_y_el_estado_de_credito(): void
    {
        $this->actingAsAdmin();

        $this->postJson(self::EXPORTAR, [])->assertStatus(422)->assertJsonValidationErrors('formato');
        $this->postJson(self::EXPORTAR, ['formato' => 'xlsx', 'estado' => 'todos'])->assertStatus(422)->assertJsonValidationErrors('estado');
        $this->postJson(self::EXPORTAR, ['formato' => 'xlsx', 'estado_credito' => 'inventado'])->assertStatus(422)->assertJsonValidationErrors('estado_credito');

        Queue::assertNothingPushed();
    }

    public function test_exporta_los_clientes_ordenados_por_nombre(): void
    {
        $this->actingAsAdmin();
        $this->crearCliente(['nombre' => 'Ana Gomez']);
        $this->crearCliente(['nombre' => 'Luis Perez']);

        $respuesta = $this->postJson(self::EXPORTAR, ['formato' => 'xlsx'])->assertStatus(202);

        $this->assertDatabaseHas('exportaciones', ['id' => $respuesta->json('exportacion.id'), 'reporte' => 'clientes.listado']);

        $this->ejecutarJobs();

        $datos = $this->leerDatos(Exportacion::find($respuesta->json('exportacion.id')));

        $this->assertCount(3, $datos, 'encabezado y dos clientes');
        $this->assertSame(['Ana Gomez', 'Luis Perez'], array_column(array_slice($datos, 1), self::NOMBRE));
    }

    public function test_la_busqueda_filtra_como_en_la_pantalla(): void
    {
        $this->actingAsAdmin();
        $this->crearCliente(['nombre' => 'Ana Gomez']);
        $this->crearCliente(['nombre' => 'Luis Perez']);

        $respuesta = $this->postJson(self::EXPORTAR, ['formato' => 'xlsx', 'search' => 'Luis'])->assertStatus(202);

        $this->ejecutarJobs();

        $nombres = $this->nombresExportados(Exportacion::find($respuesta->json('exportacion.id')));

        $this->assertSame(['Luis Perez'], $nombres);
    }

    public function test_el_asesor_con_alcance_solo_exporta_sus_clientes(): void
    {
        $admin = $this->actingAsAdmin();
        $this->crearCliente(['nombre' => 'Cliente del admin']);

        $this->actingAsRolConPermisos('asesor', ['clientes.exportar'], Empresa::find($admin->empresa_id));
        $this->crearCliente(['nombre' => 'Cliente del asesor']);

        $respuesta = $this->postJson(self::EXPORTAR, ['formato' => 'xlsx'])->assertStatus(202);

        $this->ejecutarJobs();

        $nombres = $this->nombresExportados(Exportacion::find($respuesta->json('exportacion.id')));

        $this->assertSame(['Cliente del asesor'], $nombres);
    }

    public function test_el_worker_exporta_solo_la_empresa_que_pidio_la_exportacion(): void
    {
        $this->actingAsAdmin();
        $this->crearCliente(['nombre' => 'Cliente de la empresa A']);

        $this->actingAsAdmin();
        $this->crearCliente(['nombre' => 'Cliente de la empresa B']);

        $respuesta = $this->postJson(self::EXPORTAR, ['formato' => 'xlsx'])->assertStatus(202);

        $this->salirDeSesion();
        $this->ejecutarJobs();

        $nombres = $this->nombresExportados(Exportacion::find($respuesta->json('exportacion.id')));

        $this->assertSame(['Cliente de la empresa B'], $nombres);
    }
}
