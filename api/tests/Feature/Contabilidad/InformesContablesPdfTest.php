<?php

namespace Tests\Feature\Contabilidad;

use App\Contabilidad\Services\ComprobanteService;
use App\Empresas\Models\Empresa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\AfirmaAuditoria;
use Tests\Concerns\CreatesAuditoriaFixtures;
use Tests\Concerns\CreatesContabilidadFixtures;
use Tests\TestCase;

/** PDF de balance, libro diario, libro mayor y estados financieros, con el cuadre que cada informe debe cumplir. */
class InformesContablesPdfTest extends TestCase
{
    use ActsAsAdmin;
    use AfirmaAuditoria;
    use CreatesAuditoriaFixtures;
    use CreatesContabilidadFixtures;
    use RefreshDatabase;

    private const BASE = '/api/v1/contabilidad';

    private function sembrar(Empresa $empresa): int
    {
        $this->actingAsRolConPermisos('contador', ['contabilidad.crear_manual', 'contabilidad.revisar', 'contabilidad.contabilizar', 'contabilidad.listar_comprobantes'], $empresa);
        $tipo = $this->crearTipoComprobante();
        $caja = $this->crearCuenta(['codigo' => '110505', 'nombre' => 'Caja general']);
        $ingresos = $this->crearCuenta(['codigo' => '413505', 'nombre' => 'Intereses', 'naturaleza' => 'credito', 'tipo' => 'ingreso']);

        app(ComprobanteService::class)->crearComprobante($tipo->id, '2026-02-10', [
            ['cuenta_id' => $caja->id, 'debito' => 500, 'credito' => 0],
            ['cuenta_id' => $ingresos->id, 'debito' => 0, 'credito' => 500],
        ], 'Recaudo de prueba', 'manual');

        return $caja->id;
    }

    private function informes(Empresa $empresa): void
    {
        $this->actingAsRolConPermisos('lector', [
            'informes_contables.listar_libro_diario', 'informes_contables.listar_libro_mayor', 'informes_contables.listar_balance_prueba', 'informes_contables.listar_estados_financieros',
            'informes_contables.imprimir', 'informes_contables.exportar',
        ], $empresa);
    }

    public function test_sin_autenticar_no_descarga_informes(): void
    {
        $this->getJson(self::BASE.'/balance-prueba/pdf?hasta=2026-12-31')->assertStatus(401);
        $this->getJson(self::BASE.'/libro-diario/pdf?desde=2026-01-01&hasta=2026-12-31')->assertStatus(401);
        $this->getJson(self::BASE.'/estados-financieros/pdf?desde=2026-01-01&hasta=2026-12-31')->assertStatus(401);
    }

    public function test_sin_permiso_de_impresion_no_descarga_informes(): void
    {
        $empresa = $this->crearEmpresaDePrueba();
        $this->sembrar($empresa);
        $this->actingAsRolConPermisos('solo_listar', ['informes_contables.listar_libro_diario', 'informes_contables.listar_libro_mayor', 'informes_contables.listar_balance_prueba', 'informes_contables.listar_estados_financieros', 'informes_contables.exportar'], $empresa);

        $this->get(self::BASE.'/balance-prueba/pdf?hasta=2026-12-31')->assertStatus(403);
        $this->get(self::BASE.'/libro-diario/pdf?desde=2026-01-01&hasta=2026-12-31')->assertStatus(403);
    }

    public function test_el_balance_en_pdf_exige_la_fecha_de_corte(): void
    {
        $this->actingAsAdmin();

        $this->get(self::BASE.'/balance-prueba/pdf')->assertStatus(422)->assertJsonValidationErrors('hasta');
    }

    public function test_el_libro_diario_en_pdf_rechaza_un_rango_invertido(): void
    {
        $this->actingAsAdmin();

        $this->get(self::BASE.'/libro-diario/pdf?desde=2026-12-31&hasta=2026-01-01')
            ->assertStatus(422)
            ->assertJsonValidationErrors('hasta');
    }

    public function test_descarga_el_balance_de_prueba_en_pdf_y_queda_en_bitacora(): void
    {
        $empresa = $this->crearEmpresaDePrueba();
        $this->sembrar($empresa);
        $this->informes($empresa);

        $respuesta = $this->get(self::BASE.'/balance-prueba/pdf?hasta=2026-12-31')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringStartsWith('%PDF', $respuesta->getContent());

        $eventos = $this->eventosDeAuditoria('exportar', 'contabilidad');
        $this->assertNotEmpty($eventos);
        $this->assertStringContainsString('Balance de prueba', $eventos[0]['descripcion']);
    }

    public function test_descarga_el_libro_diario_mayor_y_estados_financieros_en_pdf(): void
    {
        $empresa = $this->crearEmpresaDePrueba();
        $cajaId = $this->sembrar($empresa);
        $this->informes($empresa);

        $this->get(self::BASE.'/libro-diario/pdf?desde=2026-01-01&hasta=2026-12-31')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get(self::BASE.'/libro-mayor/pdf?cuenta_id='.$cajaId.'&desde=2026-01-01&hasta=2026-12-31')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get(self::BASE.'/estados-financieros/pdf?desde=2026-01-01&hasta=2026-12-31')->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_el_mayor_en_pdf_no_acepta_una_cuenta_de_otra_empresa(): void
    {
        $empresa = $this->crearEmpresaDePrueba();
        $otra = $this->crearEmpresaDePrueba();
        $cajaAjena = $this->sembrar($otra);
        $this->informes($empresa);

        $this->get(self::BASE.'/libro-mayor/pdf?cuenta_id='.$cajaAjena.'&desde=2026-01-01&hasta=2026-12-31')
            ->assertStatus(422)
            ->assertJsonValidationErrors('cuenta_id');
    }

    public function test_el_balance_cuadra_debitos_y_creditos(): void
    {
        $empresa = $this->crearEmpresaDePrueba();
        $this->sembrar($empresa);
        $this->informes($empresa);

        $this->getJson(self::BASE.'/balance-prueba?hasta=2026-12-31')
            ->assertOk()
            ->assertJsonPath('total_debito', '500.00')
            ->assertJsonPath('total_credito', '500.00');
    }

    public function test_el_libro_diario_cuadra_debitos_y_creditos(): void
    {
        $empresa = $this->crearEmpresaDePrueba();
        $this->sembrar($empresa);
        $this->informes($empresa);

        $this->getJson(self::BASE.'/libro-diario?desde=2026-01-01&hasta=2026-12-31')
            ->assertOk()
            ->assertJsonPath('total_debito', '500.00')
            ->assertJsonPath('total_credito', '500.00');
    }

    public function test_el_balance_de_una_empresa_no_incluye_los_movimientos_de_otra(): void
    {
        $empresa = $this->crearEmpresaDePrueba();
        $otra = $this->crearEmpresaDePrueba();
        $this->sembrar($otra);
        $this->informes($empresa);

        $this->getJson(self::BASE.'/balance-prueba?hasta=2026-12-31')
            ->assertOk()
            ->assertJsonPath('total_debito', '0.00');
    }

    public function test_los_estados_financieros_cuadran_activo_contra_pasivo_mas_patrimonio_mas_utilidad(): void
    {
        $empresa = $this->crearEmpresaDePrueba();
        $this->sembrar($empresa);
        $this->informes($empresa);

        $this->getJson(self::BASE.'/estados-financieros?desde=2026-01-01&hasta=2026-12-31')
            ->assertOk()
            ->assertJsonPath('situacion_financiera.cuadra', true)
            ->assertJsonPath('estado_resultados.utilidad', '500.00');
    }
}
