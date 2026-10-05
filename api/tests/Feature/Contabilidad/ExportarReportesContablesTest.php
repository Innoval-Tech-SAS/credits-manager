<?php

namespace Tests\Feature\Contabilidad;

use App\Contabilidad\Enums\NaturalezaCuenta;
use App\Contabilidad\Enums\TipoCuenta;
use App\Contabilidad\Services\ComprobanteService;
use App\Exportacion\Constants\ExportacionConstants;
use App\Exportacion\Jobs\ProcesarExportacionJob;
use App\Exportacion\Models\Exportacion;
use App\Exportacion\Services\ExportacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Reader\XLSX\Reader;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesContabilidadFixtures;
use Tests\TestCase;

class ExportarReportesContablesTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesContabilidadFixtures;
    use RefreshDatabase;

    private const LIBRO_DIARIO = '/api/v1/contabilidad/libro-diario/exportar';

    private const LIBRO_MAYOR = '/api/v1/contabilidad/libro-mayor/exportar';

    private const BALANCE = '/api/v1/contabilidad/balance-prueba/exportar';

    private const ESTADOS = '/api/v1/contabilidad/estados-financieros/exportar';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(ExportacionConstants::DISCO);
        Queue::fake();
    }

    /** Contabiliza un cobro en caja (débito) contra capital (crédito) por el valor dado. */
    private function contabilizarCobro(int $valor = 100, string $fecha = '2026-01-15'): array
    {
        $tipo = $this->crearTipoComprobante();
        $caja = $this->crearCuenta(['tipo' => TipoCuenta::Activo]);
        $capital = $this->crearCuenta(['naturaleza' => NaturalezaCuenta::Credito, 'tipo' => TipoCuenta::Pasivo]);

        app(ComprobanteService::class)->crearComprobante($tipo->id, $fecha, [
            ['cuenta_id' => $caja->id, 'debito' => $valor, 'credito' => 0],
            ['cuenta_id' => $capital->id, 'debito' => 0, 'credito' => $valor],
        ], 'Cobro de prueba');

        return [$caja, $capital];
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

    /** @return array<string, mixed> */
    private function cuerpoLibroDiario(string $desde = '2026-01-01', string $hasta = '2026-01-31', string $formato = 'xlsx'): array
    {
        return ['desde' => $desde, 'hasta' => $hasta, 'formato' => $formato];
    }

    public function test_sin_autenticar_recibe_401(): void
    {
        $this->postJson(self::LIBRO_DIARIO, $this->cuerpoLibroDiario())->assertStatus(401);
        $this->postJson(self::LIBRO_MAYOR, ['cuenta_id' => 1] + $this->cuerpoLibroDiario())->assertStatus(401);
        $this->postJson(self::BALANCE, ['hasta' => '2026-01-31', 'formato' => 'xlsx'])->assertStatus(401);
        $this->postJson(self::ESTADOS, $this->cuerpoLibroDiario())->assertStatus(401);

        Queue::assertNothingPushed();
    }

    public function test_asesor_sin_permiso_de_exportar_recibe_403(): void
    {
        $this->actingAsAsesor();

        $this->postJson(self::LIBRO_DIARIO, $this->cuerpoLibroDiario())->assertStatus(403);
        $this->postJson(self::BALANCE, ['hasta' => '2026-01-31', 'formato' => 'xlsx'])->assertStatus(403);

        Queue::assertNothingPushed();
    }

    public function test_valida_las_fechas_el_formato_y_la_cuenta_de_la_empresa(): void
    {
        $this->actingAsAdmin();
        $ajena = $this->crearCuenta();

        $this->actingAsAdmin();

        $this->postJson(self::LIBRO_DIARIO, [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['desde', 'hasta', 'formato']);
        $this->postJson(self::LIBRO_DIARIO, $this->cuerpoLibroDiario('2026-02-01', '2026-01-01'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('hasta');
        $this->postJson(self::LIBRO_DIARIO, $this->cuerpoLibroDiario(formato: 'pdf'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('formato');
        $this->postJson(self::LIBRO_MAYOR, ['cuenta_id' => $ajena->id] + $this->cuerpoLibroDiario())
            ->assertStatus(422)
            ->assertJsonValidationErrors('cuenta_id');

        Queue::assertNothingPushed();
    }

    public function test_libro_diario_exporta_los_movimientos_y_el_total(): void
    {
        $this->actingAsAdmin();
        $this->contabilizarCobro(100);

        $respuesta = $this->postJson(self::LIBRO_DIARIO, $this->cuerpoLibroDiario())->assertStatus(202);

        $this->assertDatabaseHas('exportaciones', ['id' => $respuesta->json('exportacion.id'), 'reporte' => 'contabilidad.libro_diario']);

        $this->ejecutarJobs();

        $datos = $this->leerDatos(Exportacion::find($respuesta->json('exportacion.id')));

        $this->assertContains('Débito', $datos[0]);
        $this->assertCount(4, $datos, 'encabezado, dos movimientos y la fila de totales');
        $this->assertSame('Totales', $datos[3][3]);
        $this->assertEquals(100, $datos[3][5]);
        $this->assertEquals(100, $datos[3][6]);
    }

    public function test_libro_mayor_exporta_el_saldo_corrido_segun_la_naturaleza(): void
    {
        $this->actingAsAdmin();
        [$caja] = $this->contabilizarCobro(100);

        $respuesta = $this->postJson(self::LIBRO_MAYOR, ['cuenta_id' => $caja->id] + $this->cuerpoLibroDiario())->assertStatus(202);

        $this->ejecutarJobs();

        $datos = $this->leerDatos(Exportacion::find($respuesta->json('exportacion.id')));

        $this->assertCount(4, $datos, 'encabezado, saldo inicial, un movimiento y totales');
        $this->assertSame('Saldo inicial', $datos[1][2]);
        $this->assertEquals(0, $datos[1][6]);
        $this->assertEquals(100, $datos[2][4]);
        $this->assertEquals(100, $datos[2][6], 'una cuenta débito sube su saldo con el débito');
    }

    public function test_balance_de_prueba_y_estados_financieros_se_exportan(): void
    {
        $this->actingAsAdmin();
        $this->contabilizarCobro(100);

        $balance = $this->postJson(self::BALANCE, ['hasta' => '2026-01-31', 'formato' => 'xlsx'])->assertStatus(202);
        $estados = $this->postJson(self::ESTADOS, $this->cuerpoLibroDiario())->assertStatus(202);

        $this->ejecutarJobs();

        $datosBalance = $this->leerDatos(Exportacion::find($balance->json('exportacion.id')));
        $datosEstados = $this->leerDatos(Exportacion::find($estados->json('exportacion.id')));

        $this->assertCount(4, $datosBalance, 'encabezado, dos cuentas y totales');
        $this->assertSame('Totales', $datosBalance[3][1]);
        $this->assertContains('Total activos', array_column($datosEstados, 2));
        $this->assertContains('Total patrimonio', array_column($datosEstados, 2));
    }

    public function test_el_worker_exporta_solo_la_empresa_de_la_solicitud_aunque_no_haya_sesion(): void
    {
        $this->actingAsAdmin();
        $this->contabilizarCobro(100);

        $this->actingAsAdmin();
        $this->contabilizarCobro(250);

        $respuesta = $this->postJson(self::LIBRO_DIARIO, $this->cuerpoLibroDiario())->assertStatus(202);

        $this->salirDeSesion();
        $this->ejecutarJobs();

        $datos = $this->leerDatos(Exportacion::find($respuesta->json('exportacion.id')));

        $this->assertCount(4, $datos, 'solo los dos movimientos de la empresa que pidió el libro');
        $this->assertEquals(250, $datos[3][5], 'el total no incluye el cobro de la otra empresa');
    }
}
