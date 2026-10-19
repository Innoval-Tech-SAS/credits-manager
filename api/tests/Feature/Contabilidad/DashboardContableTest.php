<?php

namespace Tests\Feature\Contabilidad;

use App\Contabilidad\Enums\NaturalezaCuenta;
use App\Contabilidad\Enums\TipoCuenta;
use App\Contabilidad\Services\ComprobanteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesContabilidadFixtures;
use Tests\TestCase;

class DashboardContableTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesContabilidadFixtures;
    use RefreshDatabase;

    public function test_sin_autenticar_no_consulta_el_dashboard(): void
    {
        $this->getJson('/api/v1/contabilidad/dashboard/situacion?hasta=2026-01-31')->assertStatus(401);
        $this->getJson('/api/v1/contabilidad/dashboard/resultados?desde=2026-01-01&hasta=2026-01-31')->assertStatus(401);
        $this->getJson('/api/v1/contabilidad/dashboard/cierre?hasta=2026-01-31')->assertStatus(401);
    }

    public function test_sin_permiso_no_consulta_el_dashboard(): void
    {
        $this->actingAsAsesor();

        $this->getJson('/api/v1/contabilidad/dashboard/situacion?hasta=2026-01-31')->assertStatus(403);
        $this->getJson('/api/v1/contabilidad/dashboard/resultados?desde=2026-01-01&hasta=2026-01-31')->assertStatus(403);
        $this->getJson('/api/v1/contabilidad/dashboard/cierre?hasta=2026-01-31')->assertStatus(403);
    }

    public function test_situacion_valida_la_fecha_de_corte(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/v1/contabilidad/dashboard/situacion')->assertStatus(422)->assertJsonValidationErrors('hasta');
    }

    public function test_resultados_valida_el_rango_de_fechas(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/v1/contabilidad/dashboard/resultados')->assertStatus(422)->assertJsonValidationErrors(['desde', 'hasta']);
    }

    public function test_situacion_calcula_composicion_por_grupo_y_cartera_neta(): void
    {
        $this->actingAsAdmin();
        $tipo = $this->crearTipoComprobante();
        $cxc = $this->crearCuenta(['codigo' => '130505', 'nombre' => 'Clientes']);
        $deterioro = $this->crearCuenta(['codigo' => '139905', 'nombre' => 'Deterioro de cartera', 'naturaleza' => NaturalezaCuenta::Credito, 'tipo' => TipoCuenta::Activo]);
        $patrimonio = $this->crearCuenta(['codigo' => '310505', 'nombre' => 'Capital', 'naturaleza' => NaturalezaCuenta::Credito, 'tipo' => TipoCuenta::Patrimonio]);
        $gastoDeterioro = $this->crearCuenta(['codigo' => '519905', 'nombre' => 'Gasto deterioro', 'tipo' => TipoCuenta::Gasto]);

        $servicio = app(ComprobanteService::class);
        $servicio->crearComprobante($tipo->id, '2026-01-15', [
            ['cuenta_id' => $cxc->id, 'debito' => 1000, 'credito' => 0],
            ['cuenta_id' => $patrimonio->id, 'debito' => 0, 'credito' => 1000],
        ], 'Venta a crédito');
        $servicio->crearComprobante($tipo->id, '2026-01-16', [
            ['cuenta_id' => $gastoDeterioro->id, 'debito' => 200, 'credito' => 0],
            ['cuenta_id' => $deterioro->id, 'debito' => 0, 'credito' => 200],
        ], 'Deterioro de cartera');

        $respuesta = $this->getJson('/api/v1/contabilidad/dashboard/situacion?hasta=2026-01-31')->assertOk();

        $respuesta->assertJsonPath('cuadra', true);
        $respuesta->assertJsonPath('cartera_neta.bruta', '1000.00');
        $respuesta->assertJsonPath('cartera_neta.deterioro', '200.00');
        $respuesta->assertJsonPath('cartera_neta.neta', '800.00');
        $respuesta->assertJsonPath('cartera_neta.porcentaje_deterioro', '20.00');

        $grupo13 = collect($respuesta->json('composicion_activo'))->firstWhere('codigo', '13');
        $this->assertSame('800.00', $grupo13['valor']);

        $grupo31 = collect($respuesta->json('composicion_patrimonio'))->firstWhere('codigo', '31');
        $this->assertSame('1000.00', $grupo31['valor']);
    }

    public function test_resultados_incluye_ingresos_por_cuenta_y_la_serie_mensual(): void
    {
        $this->actingAsAdmin();
        $tipo = $this->crearTipoComprobante();
        $cxc = $this->crearCuenta(['codigo' => '130505', 'nombre' => 'Clientes']);
        $ingreso = $this->crearCuenta(['codigo' => '413505', 'nombre' => 'Intereses', 'naturaleza' => NaturalezaCuenta::Credito, 'tipo' => TipoCuenta::Ingreso]);

        app(ComprobanteService::class)->crearComprobante($tipo->id, '2026-01-15', [
            ['cuenta_id' => $cxc->id, 'debito' => 500, 'credito' => 0],
            ['cuenta_id' => $ingreso->id, 'debito' => 0, 'credito' => 500],
        ], 'Venta de prueba');

        $respuesta = $this->getJson('/api/v1/contabilidad/dashboard/resultados?desde=2026-01-01&hasta=2026-01-31')->assertOk();

        $respuesta->assertJsonPath('total_ingresos', '500.00');
        $this->assertCount(1, $respuesta->json('ingresos'));
        $this->assertSame('413505', $respuesta->json('ingresos.0.codigo'));

        $serie = collect($respuesta->json('serie_mensual'));
        $this->assertCount(12, $serie);
        $this->assertSame('500.00', $serie->firstWhere('mes', '2026-01')['ingresos']);
    }

    public function test_cierre_resume_periodo_comprobantes_pendientes_y_eventos_sin_regla(): void
    {
        $this->actingAsAdmin();
        $tipo = $this->crearTipoComprobante();
        $caja = $this->crearCuenta();
        $capital = $this->crearCuenta(['naturaleza' => NaturalezaCuenta::Credito, 'tipo' => TipoCuenta::Patrimonio]);

        app(ComprobanteService::class)->crearComprobante($tipo->id, '2026-01-15', [
            ['cuenta_id' => $caja->id, 'debito' => 100, 'credito' => 0],
            ['cuenta_id' => $capital->id, 'debito' => 0, 'credito' => 100],
        ], 'Pendiente de revisión', contabilizarInmediato: false);

        $respuesta = $this->getJson('/api/v1/contabilidad/dashboard/cierre?hasta=2026-01-31')->assertOk();

        $respuesta->assertJsonPath('periodo.anio', 2026);
        $respuesta->assertJsonPath('periodo.mes', 1);
        $respuesta->assertJsonPath('periodo.estado', 'abierto');
        $respuesta->assertJsonPath('comprobantes_pendientes_revision', 1);
        $respuesta->assertJsonPath('solicitudes_reapertura_pendientes', []);
        $this->assertCount(10, $respuesta->json('eventos_sin_regla'));
        $respuesta->assertJsonPath('devengo_intereses.corrio_este_mes', false);
    }

    public function test_los_datos_de_otra_empresa_no_aparecen_en_el_dashboard(): void
    {
        $this->actingAsAdmin();
        $tipo = $this->crearTipoComprobante();
        $cxc = $this->crearCuenta(['codigo' => '130505']);
        $ingreso = $this->crearCuenta(['codigo' => '413505', 'naturaleza' => NaturalezaCuenta::Credito, 'tipo' => TipoCuenta::Ingreso]);
        app(ComprobanteService::class)->crearComprobante($tipo->id, '2026-01-15', [
            ['cuenta_id' => $cxc->id, 'debito' => 1000, 'credito' => 0],
            ['cuenta_id' => $ingreso->id, 'debito' => 0, 'credito' => 1000],
        ], 'Venta de la empresa ajena');

        $this->actingAsAdmin();

        $situacion = $this->getJson('/api/v1/contabilidad/dashboard/situacion?hasta=2026-01-31')->assertOk();
        $this->assertSame('0.00', $situacion->json('cartera_neta.bruta'));

        $cierre = $this->getJson('/api/v1/contabilidad/dashboard/cierre?hasta=2026-01-31')->assertOk();
        $this->assertSame('0.00', $cierre->json('cartera_operativa_vs_contable.saldo_contable'));
        $this->assertSame(0, $cierre->json('comprobantes_pendientes_revision'));
    }
}
