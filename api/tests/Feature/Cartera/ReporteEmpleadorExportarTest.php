<?php

namespace Tests\Feature\Cartera;

use App\Creditos\Enums\EstadoCuota;
use App\Empleadores\Models\Empleador;
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
use Tests\Concerns\AfirmaAuditoria;
use Tests\Concerns\CreatesAuditoriaFixtures;
use Tests\Concerns\CreatesCreditFixtures;
use Tests\TestCase;

class ReporteEmpleadorExportarTest extends TestCase
{
    use ActsAsAdmin;
    use AfirmaAuditoria;
    use CreatesAuditoriaFixtures;
    use CreatesCreditFixtures;
    use RefreshDatabase;

    private const PDF = '/api/v1/cartera/reporte-empleador/pdf';

    private const EXPORTAR = '/api/v1/cartera/reporte-empleador/exportar';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(ExportacionConstants::DISCO);
    }

    /** Un empleador con dos empleados y cuotas en febrero y marzo; deja la sesión del admin de su empresa. */
    private function empleadorConCuotas(): Empleador
    {
        $this->actingAsAdmin();
        $empleador = $this->crearEmpleador(['nombre' => 'Hospital del Norte SAS']);

        $ana = $this->crearCliente(['nombre' => 'Ana Gomez', 'empleador_id' => $empleador->id]);
        $creditoAna = $this->crearCredito($ana, ['valor_cuota' => 300]);
        $this->crearCuota($creditoAna, ['numero_cuota' => 1, 'fecha_vencimiento' => '2026-02-15', 'valor' => 300, 'estado' => EstadoCuota::Pagada, 'valor_pagado' => 300]);
        $this->crearCuota($creditoAna, ['numero_cuota' => 2, 'fecha_vencimiento' => '2026-03-15', 'valor' => 300]);

        $luis = $this->crearCliente(['nombre' => 'Luis Perez', 'empleador_id' => $empleador->id]);
        $creditoLuis = $this->crearCredito($luis, ['valor_cuota' => 500]);
        $this->crearCuota($creditoLuis, ['numero_cuota' => 1, 'fecha_vencimiento' => '2026-03-20', 'valor' => 500, 'estado' => EstadoCuota::Vencida]);

        return $empleador;
    }

    private function ejecutarJobEncolado(): void
    {
        Queue::assertPushed(ProcesarExportacionJob::class);

        Queue::pushed(ProcesarExportacionJob::class)->last()->handle(app(ExportacionService::class));
    }

    /** @return list<list<mixed>> */
    private function leerDatosXlsx(Exportacion $exportacion): array
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

    /** Descomprime los flujos del PDF para poder buscar el texto dibujado. */
    private function textoDe(string $pdf): string
    {
        preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdf, $flujos);

        return collect($flujos[1])->map(fn (string $flujo) => @gzuncompress($flujo) ?: '')->implode("\n");
    }

    public function test_sin_autenticar_recibe_401(): void
    {
        $this->getJson(self::PDF.'?empleador_id=1')->assertStatus(401);
        $this->postJson(self::EXPORTAR, ['empleador_id' => 1, 'formato' => 'xlsx'])->assertStatus(401);
    }

    public function test_listar_cartera_no_implica_poder_exportar(): void
    {
        $empleador = $this->empleadorConCuotas();
        $this->actingAsRolConPermisos('cartera', ['cartera.listar'], Empresa::find($empleador->empresa_id));

        $this->getJson('/api/v1/cartera/reporte-empleador?empleador_id='.$empleador->id)->assertOk();
        $this->getJson(self::PDF.'?empleador_id='.$empleador->id)->assertStatus(403);
        $this->postJson(self::EXPORTAR, ['empleador_id' => $empleador->id, 'formato' => 'xlsx'])->assertStatus(403);

        Queue::assertNothingPushed();
    }

    public function test_el_permiso_exportar_basta_para_pdf_y_excel(): void
    {
        $empleador = $this->empleadorConCuotas();
        $this->actingAsRolConPermisos('cartera', ['cartera.exportar'], Empresa::find($empleador->empresa_id));

        $this->getJson(self::PDF.'?empleador_id='.$empleador->id)->assertOk();
        $this->postJson(self::EXPORTAR, ['empleador_id' => $empleador->id, 'formato' => 'xlsx'])->assertStatus(202);
    }

    public function test_valida_el_empleador_y_el_formato(): void
    {
        $this->actingAsAdmin();

        $this->getJson(self::PDF)->assertStatus(422)->assertJsonValidationErrors('empleador_id');
        $this->getJson(self::PDF.'?empleador_id=9999')->assertStatus(422)->assertJsonValidationErrors('empleador_id');
        $this->postJson(self::EXPORTAR, ['formato' => 'xlsx'])->assertStatus(422)->assertJsonValidationErrors('empleador_id');
        $this->postJson(self::EXPORTAR, ['empleador_id' => $this->crearEmpleador()->id])->assertStatus(422)->assertJsonValidationErrors('formato');
        $this->postJson(self::EXPORTAR, ['empleador_id' => $this->crearEmpleador()->id, 'formato' => 'pdf'])->assertStatus(422)->assertJsonValidationErrors('formato');
    }

    public function test_un_empleador_de_otra_empresa_no_se_exporta(): void
    {
        $ajeno = $this->empleadorConCuotas();

        $this->actingAsAdmin();

        $this->getJson(self::PDF.'?empleador_id='.$ajeno->id)->assertStatus(422)->assertJsonValidationErrors('empleador_id');
        $this->postJson(self::EXPORTAR, ['empleador_id' => $ajeno->id, 'formato' => 'xlsx'])->assertStatus(422)->assertJsonValidationErrors('empleador_id');

        Queue::assertNothingPushed();
        $this->assertSame(0, Exportacion::query()->count());
        $this->assertSinEventosDeAuditoria('exportar');
    }

    public function test_el_pdf_sale_horizontal_con_los_empleados_los_meses_y_los_totales(): void
    {
        $empleador = $this->empleadorConCuotas();

        $respuesta = $this->get(self::PDF.'?empleador_id='.$empleador->id)->assertOk();

        $this->assertSame('application/pdf', $respuesta->headers->get('Content-Type'));
        $this->assertStringContainsString('relacion-descuento-hospital-del-norte-sas.pdf', $respuesta->headers->get('Content-Disposition'));

        $pdf = $respuesta->getContent();
        $this->assertMatchesRegularExpression('/\/MediaBox\s*\[0(\.0+)?\s+0(\.0+)?\s+792(\.0+)?\s+612(\.0+)?\]/', $pdf);

        $texto = $this->textoDe($pdf);

        foreach (['Relaci', 'Hospital del Norte SAS', 'Ana Gomez', 'Luis Perez', 'Feb 2026', 'Mar 2026', '300.00', 'Pagada', 'Vencida', 'Total por mes', '800.00'] as $esperado) {
            $this->assertTrue(str_contains($texto, $esperado), "No aparece «{$esperado}» en el PDF.");
        }
    }

    public function test_un_empleador_sin_cuotas_genera_el_pdf_sin_totales(): void
    {
        $this->actingAsAdmin();
        $empleador = $this->crearEmpleador(['nombre' => 'Sin creditos SAS']);

        $texto = $this->textoDe($this->get(self::PDF.'?empleador_id='.$empleador->id)->assertOk()->getContent());

        $this->assertTrue(str_contains($texto, 'Sin creditos SAS'));
        $this->assertFalse(str_contains($texto, 'Total por mes'));
    }

    public function test_la_descarga_del_pdf_queda_en_la_bitacora_de_la_empresa(): void
    {
        $empleador = $this->empleadorConCuotas();

        $this->get(self::PDF.'?empleador_id='.$empleador->id)->assertOk();

        $evento = $this->assertEventoAuditoria('exportar', 'cartera', 'empleador', $empleador->id);
        $this->assertSame($empleador->empresa_id, $evento['empresa_id']);
    }

    public function test_pedir_el_excel_responde_202_encola_y_audita(): void
    {
        $empleador = $this->empleadorConCuotas();

        $respuesta = $this->postJson(self::EXPORTAR, ['empleador_id' => $empleador->id, 'formato' => 'xlsx'])->assertStatus(202);

        $respuesta->assertJsonPath('exportacion.estado', 'pendiente')->assertJsonPath('exportacion.filas', 3);
        $this->assertDatabaseHas('exportaciones', ['id' => $respuesta->json('exportacion.id'), 'reporte' => 'cartera.reporte_empleador', 'empresa_id' => $empleador->empresa_id]);
        Queue::assertPushed(ProcesarExportacionJob::class);

        $evento = $this->assertEventoAuditoria('exportar', 'cartera', 'exportacion', $respuesta->json('exportacion.id'));
        $this->assertSame(['empleador' => 'Hospital del Norte SAS'], $evento['valores_nuevos']['filtros']);
    }

    public function test_el_xlsx_trae_una_columna_de_valor_y_otra_de_estado_por_mes_y_la_fila_de_totales(): void
    {
        $empleador = $this->empleadorConCuotas();

        $id = $this->postJson(self::EXPORTAR, ['empleador_id' => $empleador->id, 'formato' => 'xlsx'])->assertStatus(202)->json('exportacion.id');
        $this->ejecutarJobEncolado();

        $filas = $this->leerDatosXlsx(Exportacion::query()->findOrFail($id));

        $this->assertSame(['Empleado', 'Cuota', 'Feb 2026', 'Estado Feb 2026', 'Mar 2026', 'Estado Mar 2026'], $filas[0]);
        $this->assertSame('Ana Gomez', $filas[1][0]);
        $this->assertEquals(300, $filas[1][2]);
        $this->assertSame('Pagada', $filas[1][3]);
        $this->assertSame('Luis Perez', $filas[2][0]);
        $this->assertSame('', $filas[2][2], 'Luis no tiene cuota en febrero: la celda queda vacía.');
        $this->assertSame('Vencida', $filas[2][5]);
        $this->assertSame('Total por mes', $filas[3][0]);
        $this->assertEquals(300, $filas[3][2]);
        $this->assertEquals(800, $filas[3][4]);
        $this->assertCount(4, $filas);
    }
}
