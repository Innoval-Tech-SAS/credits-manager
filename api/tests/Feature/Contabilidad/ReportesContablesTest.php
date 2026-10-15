<?php

namespace Tests\Feature\Contabilidad;

use App\Contabilidad\Enums\NaturalezaCuenta;
use App\Contabilidad\Enums\TipoCuenta;
use App\Contabilidad\Services\ComprobanteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesContabilidadFixtures;
use Tests\TestCase;

class ReportesContablesTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesContabilidadFixtures;
    use RefreshDatabase;

    /** Contabiliza un cobro de 100 (caja debita, capital acreedor) en la fecha dada; devuelve ambas cuentas. */
    private function contabilizarCobro(string $fecha = '2026-01-15'): array
    {
        $tipo = $this->crearTipoComprobante();
        $caja = $this->crearCuenta(['tipo' => TipoCuenta::Activo]);
        $capital = $this->crearCuenta(['naturaleza' => NaturalezaCuenta::Credito, 'tipo' => TipoCuenta::Pasivo]);

        app(ComprobanteService::class)->crearComprobante($tipo->id, $fecha, [
            ['cuenta_id' => $caja->id, 'debito' => 100, 'credito' => 0],
            ['cuenta_id' => $capital->id, 'debito' => 0, 'credito' => 100],
        ], 'Cobro de prueba');

        return [$caja, $capital];
    }

    public function test_sin_autenticar_no_consulta_los_reportes(): void
    {
        $this->getJson('/api/v1/contabilidad/libro-diario')->assertStatus(401);
        $this->getJson('/api/v1/contabilidad/libro-mayor?cuenta_id=1')->assertStatus(401);
        $this->getJson('/api/v1/contabilidad/balance-prueba')->assertStatus(401);
        $this->getJson('/api/v1/contabilidad/estados-financieros')->assertStatus(401);
    }

    public function test_libro_diario_lista_los_movimientos_del_rango(): void
    {
        $this->actingAsAdmin();
        $this->contabilizarCobro('2026-01-15');

        $dentro = $this->getJson('/api/v1/contabilidad/libro-diario?desde=2026-01-01&hasta=2026-01-31')->assertOk();
        $fuera = $this->getJson('/api/v1/contabilidad/libro-diario?desde=2026-02-01&hasta=2026-02-28')->assertOk();

        $this->assertCount(2, $dentro->json('movimientos'));
        $this->assertCount(0, $fuera->json('movimientos'));
    }

    public function test_libro_diario_incluye_el_nombre_del_tercero_de_la_linea(): void
    {
        $this->actingAsAdmin();
        $tipo = $this->crearTipoComprobante();
        $caja = $this->crearCuenta(['tipo' => TipoCuenta::Activo]);
        $cxp = $this->crearCuenta(['naturaleza' => NaturalezaCuenta::Credito, 'tipo' => TipoCuenta::Pasivo]);
        $tercero = $this->crearTercero(['nombre' => 'Proveedor de prueba']);

        app(ComprobanteService::class)->crearComprobante($tipo->id, '2026-01-15', [
            ['cuenta_id' => $caja->id, 'debito' => 100, 'credito' => 0],
            ['cuenta_id' => $cxp->id, 'debito' => 0, 'credito' => 100, 'tercero_id' => $tercero->id],
        ], 'Causación con tercero');

        $respuesta = $this->getJson('/api/v1/contabilidad/libro-diario?desde=2026-01-01&hasta=2026-01-31')->assertOk();

        $movimientos = collect($respuesta->json('movimientos'));
        $this->assertSame($tercero->nombre, $movimientos->firstWhere('cuenta_codigo', $cxp->codigo)['tercero']);
        $this->assertNull($movimientos->firstWhere('cuenta_codigo', $caja->codigo)['tercero']);
    }

    public function test_libro_mayor_calcula_el_saldo_inicial_y_los_movimientos(): void
    {
        $this->actingAsAdmin();
        [$caja] = $this->contabilizarCobro('2026-01-15');

        $respuesta = $this->getJson("/api/v1/contabilidad/libro-mayor?cuenta_id={$caja->id}&desde=2026-01-01&hasta=2026-01-31")->assertOk();

        $this->assertCount(1, $respuesta->json('movimientos'));
        $this->assertSame('0.00', $respuesta->json('saldo_inicial'));
    }

    public function test_balance_de_prueba_cuadra_debitos_y_creditos(): void
    {
        $this->actingAsAdmin();
        $this->contabilizarCobro();

        $respuesta = $this->getJson('/api/v1/contabilidad/balance-prueba?hasta=2026-01-31')->assertOk();

        $this->assertCount(2, $respuesta->json('saldos'));
        $this->assertSame($respuesta->json('total_debito'), $respuesta->json('total_credito'));
    }

    public function test_estados_financieros_cuadran_activos_contra_pasivo_mas_patrimonio(): void
    {
        $this->actingAsAdmin();
        $this->contabilizarCobro();

        $respuesta = $this->getJson('/api/v1/contabilidad/estados-financieros?desde=2026-01-01&hasta=2026-01-31')->assertOk();

        $this->assertTrue($respuesta->json('situacion_financiera.cuadra'));
    }

    public function test_los_reportes_no_muestran_movimientos_de_otra_empresa(): void
    {
        $this->actingAsAdmin();
        $this->contabilizarCobro('2026-01-15');

        $this->actingAsAdmin();

        $this->getJson('/api/v1/contabilidad/libro-diario?desde=2026-01-01&hasta=2026-01-31')->assertOk()->assertJsonCount(0, 'movimientos');
        $this->getJson('/api/v1/contabilidad/balance-prueba?hasta=2026-01-31')->assertOk()->assertJsonCount(0, 'saldos');
    }

    public function test_libro_mayor_de_una_cuenta_ajena_responde_404(): void
    {
        $this->actingAsAdmin();
        [$caja] = $this->contabilizarCobro();

        $this->actingAsAdmin();

        $this->getJson("/api/v1/contabilidad/libro-mayor?cuenta_id={$caja->id}&desde=2026-01-01&hasta=2026-01-31")->assertStatus(404);
    }
}
