<?php

namespace Tests\Feature\Contabilidad;

use App\Contabilidad\Models\SaldoApertura;
use App\Contabilidad\Services\ComprobanteService;
use App\Empresas\Models\Empresa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesAuditoriaFixtures;
use Tests\Concerns\CreatesContabilidadFixtures;
use Tests\TestCase;

/** Saldos de apertura cargados contra el balance de prueba, cuenta por cuenta, antes de poner en producción. */
class ConciliacionAperturaTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesAuditoriaFixtures;
    use CreatesContabilidadFixtures;
    use RefreshDatabase;

    private const URL = '/api/v1/contabilidad/conciliacion-apertura';

    /** Carga los saldos iniciales de caja (débito) y de capital (crédito), como lo hace la importación de saldos. */
    private function cargarSaldos(Empresa $empresa, int $monto): array
    {
        $this->actingAsRolConPermisos('carga_saldos', ['configuracion_contable.crear', 'contabilidad.crear_manual'], $empresa);
        $caja = $this->crearCuenta(['codigo' => '110505', 'nombre' => 'Caja general']);
        $capital = $this->crearCuenta(['codigo' => '310505', 'nombre' => 'Capital social', 'naturaleza' => 'credito', 'tipo' => 'patrimonio']);

        SaldoApertura::query()->create(['cuenta_id' => $caja->id, 'fecha_corte' => '2026-01-31', 'debito' => $monto, 'credito' => 0]);
        SaldoApertura::query()->create(['cuenta_id' => $capital->id, 'fecha_corte' => '2026-01-31', 'debito' => 0, 'credito' => $monto]);

        return [$caja, $capital];
    }

    private function lector(Empresa $empresa): void
    {
        $this->actingAsRolConPermisos('auditor_apertura', ['informes_contables.listar'], $empresa);
    }

    public function test_sin_autenticar_no_concilia(): void
    {
        $this->getJson(self::URL.'?hasta=2026-01-31')->assertStatus(401);
    }

    public function test_sin_permiso_de_informes_no_concilia(): void
    {
        $empresa = $this->crearEmpresaDePrueba();
        $this->actingAsAsesor($empresa);

        $this->getJson(self::URL.'?hasta=2026-01-31')->assertStatus(403);
    }

    public function test_exige_la_fecha_de_corte(): void
    {
        $this->actingAsAdmin();

        $this->getJson(self::URL)->assertStatus(422)->assertJsonValidationErrors('hasta');
    }

    public function test_sin_saldos_cargados_y_sin_movimientos_cuadra(): void
    {
        $empresa = $this->crearEmpresaDePrueba();
        $this->lector($empresa);

        $this->getJson(self::URL.'?hasta=2026-01-31')
            ->assertOk()
            ->assertJsonPath('cuadra', true)
            ->assertJsonPath('cuentas_con_diferencia', 0);
    }

    public function test_un_saldo_cargado_sin_contabilizar_aparece_como_diferencia_por_cuenta(): void
    {
        $empresa = $this->crearEmpresaDePrueba();
        [$caja] = $this->cargarSaldos($empresa, 1000);
        $this->lector($empresa);

        $respuesta = $this->getJson(self::URL.'?hasta=2026-01-31')
            ->assertOk()
            ->assertJsonPath('cuadra', false)
            ->assertJsonPath('cuentas_con_diferencia', 2);

        $fila = collect($respuesta->json('filas'))->firstWhere('codigo', '110505');
        $this->assertSame('1000.00', $fila['cargado_debito']);
        $this->assertSame('0.00', $fila['balance_debito']);
        $this->assertSame('1000.00', $fila['diferencia']);
    }

    public function test_cuadra_cuando_la_apertura_queda_contabilizada_con_los_mismos_saldos(): void
    {
        $empresa = $this->crearEmpresaDePrueba();
        [$caja, $capital] = $this->cargarSaldos($empresa, 1000);
        $tipo = $this->crearTipoComprobante();

        app(ComprobanteService::class)->crearComprobante($tipo->id, '2026-01-31', [
            ['cuenta_id' => $caja->id, 'debito' => 1000, 'credito' => 0],
            ['cuenta_id' => $capital->id, 'debito' => 0, 'credito' => 1000],
        ], 'Apertura de prueba', 'manual');

        $this->lector($empresa);

        $this->getJson(self::URL.'?hasta=2026-01-31')
            ->assertOk()
            ->assertJsonPath('cuadra', true)
            ->assertJsonPath('cuentas_con_diferencia', 0)
            ->assertJsonPath('total_cargado_debito', '1000.00')
            ->assertJsonPath('total_balance_debito', '1000.00');
    }

    public function test_los_saldos_de_otra_empresa_no_aparecen(): void
    {
        $empresa = $this->crearEmpresaDePrueba();
        $otra = $this->crearEmpresaDePrueba();
        $this->cargarSaldos($otra, 750);
        $this->lector($empresa);

        $this->getJson(self::URL.'?hasta=2026-01-31')
            ->assertOk()
            ->assertJsonPath('cuadra', true)
            ->assertJsonPath('filas', []);
    }
}
