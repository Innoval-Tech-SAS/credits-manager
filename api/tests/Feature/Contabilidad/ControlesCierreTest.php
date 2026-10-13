<?php

namespace Tests\Feature\Contabilidad;

use App\Contabilidad\Enums\NaturalezaCuenta;
use App\Contabilidad\Enums\NaturalezaDocumento;
use App\Contabilidad\Enums\TipoCuenta;
use App\Contabilidad\Models\DocumentoTercero;
use App\Contabilidad\Services\ComprobanteService;
use App\Contabilidad\Services\DocumentoTerceroService;
use App\Core\Dinero\Dinero;
use App\Empresas\Models\Empresa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesAuditoriaFixtures;
use Tests\Concerns\CreatesContabilidadFixtures;
use Tests\TestCase;

/** Controles de cierre mensual (6.6.3): auxiliar vs balance, saldos contrarios y estado/saldo inconsistente. */
class ControlesCierreTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesAuditoriaFixtures;
    use CreatesContabilidadFixtures;
    use RefreshDatabase;

    private const URL = '/api/v1/contabilidad/controles-cierre';

    private function lector(Empresa $empresa): void
    {
        $this->actingAsRolConPermisos('auditor_cierre', ['informes_contables.listar_controles_cierre'], $empresa);
    }

    public function test_sin_autenticar_no_corre_los_controles(): void
    {
        $this->getJson(self::URL.'?hasta=2026-01-31')->assertStatus(401);
    }

    public function test_sin_permiso_no_corre_los_controles(): void
    {
        $empresa = $this->crearEmpresaDePrueba();
        $this->actingAsAsesor($empresa);

        $this->getJson(self::URL.'?hasta=2026-01-31')->assertStatus(403);
    }

    public function test_sin_datos_todo_cuadra(): void
    {
        $empresa = $this->crearEmpresaDePrueba();
        $this->lector($empresa);

        $respuesta = $this->getJson(self::URL.'?hasta=2026-01-31')->assertOk();

        $respuesta->assertJsonPath('cuentas_por_cobrar.cuadra', true);
        $respuesta->assertJsonPath('cuentas_por_pagar.cuadra', true);
        $respuesta->assertJsonPath('saldos_contrarios.contador', 0);
        $respuesta->assertJsonPath('documentos_inconsistentes.contador', 0);
    }

    public function test_cuadra_cuentas_por_cobrar_cuando_el_auxiliar_coincide_con_el_balance(): void
    {
        $empresa = $this->crearEmpresaDePrueba();
        $this->actingAsRolConPermisos('carga', ['configuracion_contable.crear_plan_cuentas', 'contabilidad.crear_manual'], $empresa);
        $cxc = $this->crearCuenta(['codigo' => '130505', 'nombre' => 'Clientes']);
        $ingreso = $this->crearCuenta(['codigo' => '413505', 'nombre' => 'Ingreso', 'naturaleza' => NaturalezaCuenta::Credito, 'tipo' => TipoCuenta::Ingreso]);
        $tipo = $this->crearTipoComprobante();

        app(ComprobanteService::class)->crearComprobante($tipo->id, '2026-01-15', [
            ['cuenta_id' => $cxc->id, 'debito' => 1000, 'credito' => 0],
            ['cuenta_id' => $ingreso->id, 'debito' => 0, 'credito' => 1000],
        ], 'Venta de prueba', 'manual');

        $this->lector($empresa);

        $respuesta = $this->getJson(self::URL.'?hasta=2026-01-31')->assertOk();

        $respuesta->assertJsonPath('cuentas_por_cobrar.cuadra', true);
        $respuesta->assertJsonPath('cuentas_por_cobrar.total_auxiliar', '1000.00');
        $respuesta->assertJsonPath('cuentas_por_cobrar.total_balance', '1000.00');
    }

    public function test_detecta_un_saldo_contrario_en_una_cuenta_por_cobrar(): void
    {
        $empresa = $this->crearEmpresaDePrueba();
        $this->actingAsRolConPermisos('carga', ['configuracion_contable.crear_plan_cuentas', 'contabilidad.crear_manual'], $empresa);
        $cxc = $this->crearCuenta(['codigo' => '130505', 'nombre' => 'Clientes']);
        $ingreso = $this->crearCuenta(['codigo' => '413505', 'nombre' => 'Ingreso', 'naturaleza' => NaturalezaCuenta::Credito, 'tipo' => TipoCuenta::Ingreso]);
        $tipo = $this->crearTipoComprobante();

        // Una 1305 con saldo CRÉDITO (al revés de lo esperado para una cuenta deudora).
        app(ComprobanteService::class)->crearComprobante($tipo->id, '2026-01-15', [
            ['cuenta_id' => $ingreso->id, 'debito' => 500, 'credito' => 0],
            ['cuenta_id' => $cxc->id, 'debito' => 0, 'credito' => 500],
        ], 'Reverso mal clasificado', 'manual');

        $this->lector($empresa);

        $respuesta = $this->getJson(self::URL.'?hasta=2026-01-31')->assertOk();

        $respuesta->assertJsonPath('saldos_contrarios.contador', 1);
        $this->assertSame('130505', $respuesta->json('saldos_contrarios.filas.0.codigo'));
    }

    public function test_detecta_un_documento_con_estado_inconsistente_con_su_saldo(): void
    {
        $empresa = $this->crearEmpresaDePrueba();
        $this->actingAsAdmin($empresa);
        $tercero = $this->crearTercero();
        $servicio = app(DocumentoTerceroService::class);
        $documento = $servicio->crearDocumento($tercero, NaturalezaDocumento::PorPagar, 'factura_compra', 'FC-001', '2026-01-15', 'Compra', Dinero::desdePesos(1000), 'facturas', 1);

        // Fuerza la inconsistencia a mano: saldo sigue en 1000 pero el estado dice "cancelado".
        DocumentoTercero::whereKey($documento->id)->update(['estado' => 'cancelado']);

        $this->lector($empresa);

        $respuesta = $this->getJson(self::URL.'?hasta=2026-01-31')->assertOk();

        $respuesta->assertJsonPath('documentos_inconsistentes.contador', 1);
    }

    public function test_los_datos_de_otra_empresa_no_aparecen(): void
    {
        $empresa = $this->crearEmpresaDePrueba();
        $otra = $this->crearEmpresaDePrueba();

        $this->actingAsRolConPermisos('carga', ['configuracion_contable.crear_plan_cuentas', 'contabilidad.crear_manual'], $otra);
        $cxc = $this->crearCuenta(['codigo' => '130505', 'nombre' => 'Clientes']);
        $ingreso = $this->crearCuenta(['codigo' => '413505', 'nombre' => 'Ingreso', 'naturaleza' => NaturalezaCuenta::Credito, 'tipo' => TipoCuenta::Ingreso]);
        app(ComprobanteService::class)->crearComprobante($this->crearTipoComprobante()->id, '2026-01-15', [
            ['cuenta_id' => $cxc->id, 'debito' => 1000, 'credito' => 0],
            ['cuenta_id' => $ingreso->id, 'debito' => 0, 'credito' => 1000],
        ], 'Venta de la otra empresa', 'manual');

        $this->lector($empresa);

        $respuesta = $this->getJson(self::URL.'?hasta=2026-01-31')->assertOk();

        $respuesta->assertJsonPath('cuentas_por_cobrar.total_balance', '0.00');
    }
}
