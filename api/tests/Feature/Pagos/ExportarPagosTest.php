<?php

namespace Tests\Feature\Pagos;

use App\Empresas\Models\Empresa;
use App\Exportacion\Constants\ExportacionConstants;
use App\Exportacion\Jobs\ProcesarExportacionJob;
use App\Exportacion\Models\Exportacion;
use App\Exportacion\Services\ExportacionService;
use App\Pagos\Enums\FormaPago;
use App\Pagos\Models\Pago;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Reader\XLSX\Reader;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesAuditoriaFixtures;
use Tests\Concerns\CreatesCreditFixtures;
use Tests\TestCase;

class ExportarPagosTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesAuditoriaFixtures;
    use CreatesCreditFixtures;
    use RefreshDatabase;

    private const EXPORTAR = '/api/v1/pagos/exportar';

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

    /** Un pago de $valor de un cliente con nombre, sobre un crédito suyo, en la sesión actual. */
    private function pagoDe(string $nombre, int $valor): Pago
    {
        $cliente = $this->crearCliente(['nombre' => $nombre]);
        $credito = $this->crearCredito($cliente);

        return Pago::create([
            'credito_id' => $credito->id,
            'cliente_id' => $cliente->id,
            'valor_pago' => $valor,
            'fecha_pago' => '2026-02-01',
            'forma_pago' => FormaPago::Transferencia,
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

    public function test_exporta_los_pagos_con_su_total(): void
    {
        $this->actingAsAdmin();
        $this->pagoDe('Ana Gomez', 250);
        $this->pagoDe('Luis Perez', 100);

        $respuesta = $this->postJson(self::EXPORTAR, ['formato' => 'xlsx'])->assertStatus(202);

        $this->assertDatabaseHas('exportaciones', ['id' => $respuesta->json('exportacion.id'), 'reporte' => 'pagos.listado']);

        $this->ejecutarJobs();

        $datos = $this->leerDatos(Exportacion::find($respuesta->json('exportacion.id')));

        $this->assertCount(4, $datos, 'encabezado, dos pagos y el total');
        $this->assertSame('Total', $datos[3][2]);
        $this->assertEquals(350, $datos[3][5]);
    }

    public function test_la_busqueda_filtra_como_en_la_pantalla(): void
    {
        $this->actingAsAdmin();
        $this->pagoDe('Ana Gomez', 250);
        $this->pagoDe('Luis Perez', 100);

        $respuesta = $this->postJson(self::EXPORTAR, ['formato' => 'xlsx', 'search' => 'Ana'])->assertStatus(202);

        $this->ejecutarJobs();

        $nombres = array_column($this->leerDatos(Exportacion::find($respuesta->json('exportacion.id'))), 2);

        $this->assertContains('Ana Gomez', $nombres);
        $this->assertNotContains('Luis Perez', $nombres);
    }

    public function test_el_asesor_con_alcance_solo_exporta_los_pagos_de_sus_clientes(): void
    {
        $admin = $this->actingAsAdmin();
        $this->pagoDe('Pago del admin', 100);

        $this->actingAsRolConPermisos('asesor', ['pagos.exportar_listado'], Empresa::find($admin->empresa_id));
        $this->pagoDe('Pago del asesor', 200);

        $respuesta = $this->postJson(self::EXPORTAR, ['formato' => 'xlsx'])->assertStatus(202);

        $this->ejecutarJobs();

        $nombres = array_column($this->leerDatos(Exportacion::find($respuesta->json('exportacion.id'))), 2);

        $this->assertContains('Pago del asesor', $nombres);
        $this->assertNotContains('Pago del admin', $nombres);
    }

    public function test_el_worker_exporta_solo_la_empresa_que_pidio_la_exportacion(): void
    {
        $this->actingAsAdmin();
        $this->pagoDe('Pago de la empresa A', 100);

        $this->actingAsAdmin();
        $this->pagoDe('Pago de la empresa B', 900);

        $respuesta = $this->postJson(self::EXPORTAR, ['formato' => 'xlsx'])->assertStatus(202);

        $this->salirDeSesion();
        $this->ejecutarJobs();

        $datos = $this->leerDatos(Exportacion::find($respuesta->json('exportacion.id')));
        $nombres = array_column($datos, 2);

        $this->assertContains('Pago de la empresa B', $nombres);
        $this->assertNotContains('Pago de la empresa A', $nombres);
        $this->assertEquals(900, $datos[2][5], 'el total no incluye el pago de la otra empresa');
    }
}
