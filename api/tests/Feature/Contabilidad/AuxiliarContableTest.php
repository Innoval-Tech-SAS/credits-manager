<?php

namespace Tests\Feature\Contabilidad;

use App\Contabilidad\Models\PlanCuenta;
use App\Contabilidad\Services\ComprobanteService;
use App\Empresas\Models\Empresa;
use App\Exportacion\Constants\ExportacionConstants;
use App\Exportacion\Jobs\ProcesarExportacionJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesAuditoriaFixtures;
use Tests\Concerns\CreatesContabilidadFixtures;
use Tests\TestCase;

/** Auxiliares por cuenta y por tercero: consulta, exportación y PDF, con cuadre entre saldo inicial, movimientos y final. */
class AuxiliarContableTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesAuditoriaFixtures;
    use CreatesContabilidadFixtures;
    use RefreshDatabase;

    private const BASE = '/api/v1/contabilidad/auxiliares';

    private PlanCuenta $caja;

    private PlanCuenta $ingresos;

    /** Dos comprobantes contabilizados: uno en enero (saldo inicial) y uno en febrero (movimiento del rango). */
    private function sembrarMovimientos(Empresa $empresa): array
    {
        $this->actingAsRolConPermisos('contador_siembra', ['contabilidad.crear_manual', 'contabilidad.revisar', 'contabilidad.contabilizar', 'contabilidad.listar_comprobantes'], $empresa);
        $tipo = $this->crearTipoComprobante();
        $this->caja = $this->crearCuenta(['codigo' => '110505', 'nombre' => 'Caja general']);
        $this->ingresos = $this->crearCuenta(['codigo' => '413505', 'nombre' => 'Intereses', 'naturaleza' => 'credito', 'tipo' => 'ingreso']);
        $tercero = $this->crearTercero(['nombre' => 'Deudor Uno']);

        $servicio = app(ComprobanteService::class);
        $enero = $servicio->crearComprobante($tipo->id, '2026-01-10', [
            ['cuenta_id' => $this->caja->id, 'debito' => 1000, 'credito' => 0, 'tercero_id' => $tercero->id],
            ['cuenta_id' => $this->ingresos->id, 'debito' => 0, 'credito' => 1000],
        ], 'Enero', 'manual');
        $febrero = $servicio->crearComprobante($tipo->id, '2026-02-05', [
            ['cuenta_id' => $this->caja->id, 'debito' => 300, 'credito' => 0, 'tercero_id' => $tercero->id],
            ['cuenta_id' => $this->ingresos->id, 'debito' => 0, 'credito' => 300],
        ], 'Febrero', 'manual');

        return [$enero, $febrero, $tercero];
    }

    public function test_sin_autenticar_no_consulta_auxiliares(): void
    {
        $this->getJson(self::BASE.'?desde=2026-01-01&hasta=2026-12-31&cuenta_id=1')->assertStatus(401);
    }

    public function test_sin_permiso_de_consulta_no_ve_auxiliares(): void
    {
        $empresa = $this->crearEmpresaDePrueba();
        $this->actingAsAsesor($empresa);

        $this->getJson(self::BASE.'?desde=2026-01-01&hasta=2026-12-31&cuenta_id=1')->assertStatus(403);
    }

    public function test_exige_cuenta_o_tercero(): void
    {
        $this->actingAsAdmin();

        $this->getJson(self::BASE.'?desde=2026-01-01&hasta=2026-12-31')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['cuenta_id', 'tercero_id']);
    }

    public function test_el_saldo_inicial_mas_movimientos_da_el_saldo_final_por_cuenta(): void
    {
        $empresa = $this->crearEmpresaDePrueba();
        $this->sembrarMovimientos($empresa);
        $this->actingAsRolConPermisos('auxiliar', ['informes_contables.listar_auxiliares'], $empresa);

        $respuesta = $this->getJson(self::BASE.'?desde=2026-02-01&hasta=2026-02-28&cuenta_id='.$this->caja->id)
            ->assertOk();

        $grupo = $respuesta->json('grupos.0');
        $this->assertSame('110505', $grupo['cuenta']['codigo']);
        $this->assertSame('1000.00', $grupo['saldo_inicial']);
        $this->assertSame('300.00', $grupo['total_debito']);
        $this->assertSame('0.00', $grupo['total_credito']);
        $this->assertSame('1300.00', $grupo['saldo_final']);
        $this->assertCount(1, $grupo['movimientos']);
        $this->assertSame('Deudor Uno', $grupo['movimientos'][0]['tercero']);
    }

    public function test_el_auxiliar_por_tercero_agrupa_por_cuenta(): void
    {
        $empresa = $this->crearEmpresaDePrueba();
        [, , $tercero] = $this->sembrarMovimientos($empresa);
        $this->actingAsRolConPermisos('auxiliar', ['informes_contables.listar_auxiliares'], $empresa);

        $respuesta = $this->getJson(self::BASE.'?desde=2026-01-01&hasta=2026-12-31&tercero_id='.$tercero->id)
            ->assertOk();

        // Solo la cuenta de caja lleva tercero: las líneas de ingreso se crearon sin él.
        $grupos = collect($respuesta->json('grupos'));
        $this->assertSame(['110505'], $grupos->pluck('cuenta.codigo')->all());
        $this->assertSame('0.00', $grupos->first()['saldo_inicial']);
        $this->assertSame('1300.00', $grupos->first()['saldo_final']);
    }

    public function test_los_movimientos_de_caja_e_ingresos_cuadran_entre_si(): void
    {
        $empresa = $this->crearEmpresaDePrueba();
        $this->sembrarMovimientos($empresa);
        $this->actingAsRolConPermisos('auxiliar', ['informes_contables.listar_auxiliares'], $empresa);

        $caja = $this->getJson(self::BASE.'?desde=2026-01-01&hasta=2026-12-31&cuenta_id='.$this->caja->id)->assertOk();
        $ingresos = $this->getJson(self::BASE.'?desde=2026-01-01&hasta=2026-12-31&cuenta_id='.$this->ingresos->id)->assertOk();

        // Cada comprobante es un asiento de partida doble: lo que entra por débito en caja sale por crédito en ingresos.
        $this->assertSame('1300.00', $caja->json('total_debito'));
        $this->assertSame('1300.00', $ingresos->json('total_credito'));
        $this->assertSame($caja->json('total_debito'), $ingresos->json('total_credito'));
    }

    public function test_un_tercero_con_cuenta_sin_movimientos_muestra_la_cuenta_en_cero(): void
    {
        $empresa = $this->crearEmpresaDePrueba();
        $this->sembrarMovimientos($empresa);
        $cuentaVacia = $this->crearCuenta(['codigo' => '999905', 'nombre' => 'Sin movimiento']);
        $this->actingAsRolConPermisos('auxiliar', ['informes_contables.listar_auxiliares'], $empresa);

        $this->getJson(self::BASE.'?desde=2026-01-01&hasta=2026-12-31&cuenta_id='.$cuentaVacia->id)
            ->assertOk()
            ->assertJsonPath('grupos.0.cuenta.codigo', '999905')
            ->assertJsonPath('grupos.0.saldo_final', '0.00');
    }

    public function test_un_auxiliar_de_otra_empresa_responde_422_al_filtrar(): void
    {
        $empresa = $this->crearEmpresaDePrueba();
        $otra = $this->crearEmpresaDePrueba();
        $this->sembrarMovimientos($otra);
        $this->actingAsRolConPermisos('auxiliar', ['informes_contables.listar_auxiliares'], $empresa);

        $this->getJson(self::BASE.'?desde=2026-01-01&hasta=2026-12-31&cuenta_id='.$this->caja->id)
            ->assertStatus(422)
            ->assertJsonValidationErrors('cuenta_id');
    }

    public function test_exporta_el_auxiliar_en_segundo_plano(): void
    {
        Storage::fake(ExportacionConstants::DISCO);
        Queue::fake();
        $empresa = $this->crearEmpresaDePrueba();
        $this->sembrarMovimientos($empresa);
        $this->actingAsRolConPermisos('exportador', ['informes_contables.listar_auxiliares', 'informes_contables.exportar'], $empresa);

        $this->postJson(self::BASE.'/exportar', [
            'cuenta_id' => $this->caja->id,
            'desde' => '2026-01-01',
            'hasta' => '2026-12-31',
            'formato' => 'xlsx',
        ])->assertStatus(202);

        Queue::assertPushed(ProcesarExportacionJob::class);
    }

    public function test_exportar_sin_permiso_de_exportacion_responde_403(): void
    {
        $empresa = $this->crearEmpresaDePrueba();
        $this->sembrarMovimientos($empresa);
        $this->actingAsRolConPermisos('solo_lectura', ['informes_contables.listar_auxiliares'], $empresa);

        $this->postJson(self::BASE.'/exportar', [
            'cuenta_id' => $this->caja->id,
            'desde' => '2026-01-01',
            'hasta' => '2026-12-31',
            'formato' => 'csv',
        ])->assertStatus(403);
    }

    public function test_descarga_el_auxiliar_en_pdf(): void
    {
        $empresa = $this->crearEmpresaDePrueba();
        [, , $tercero] = $this->sembrarMovimientos($empresa);
        $this->actingAsRolConPermisos('imprime', ['informes_contables.listar_auxiliares', 'informes_contables.imprimir'], $empresa);

        $respuesta = $this->get(self::BASE.'/pdf?desde=2026-01-01&hasta=2026-12-31&tercero_id='.$tercero->id)
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringStartsWith('%PDF', $respuesta->getContent());
    }

    public function test_el_pdf_del_auxiliar_exige_permiso_de_impresion(): void
    {
        $empresa = $this->crearEmpresaDePrueba();
        $this->sembrarMovimientos($empresa);
        $this->actingAsRolConPermisos('sin_imprimir', ['informes_contables.listar_auxiliares', 'informes_contables.exportar'], $empresa);

        $this->get(self::BASE.'/pdf?desde=2026-01-01&hasta=2026-12-31&cuenta_id='.$this->caja->id)->assertStatus(403);
    }

    public function test_sin_autenticar_no_descarga_el_pdf(): void
    {
        $this->getJson(self::BASE.'/pdf?desde=2026-01-01&hasta=2026-12-31&cuenta_id=1')->assertStatus(401);
    }
}
