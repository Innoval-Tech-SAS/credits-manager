<?php

namespace Tests\Feature\Contabilidad;

use App\Contabilidad\Enums\EventoContable;
use App\Contabilidad\Models\Comprobante;
use App\Contabilidad\Models\PlanCuenta;
use App\Contabilidad\Services\ComprobanteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesContabilidadFixtures;
use Tests\Concerns\CreatesCreditFixtures;
use Tests\Concerns\CreatesGastosFixtures;
use Tests\TestCase;

/** Los asientos solo usan cuentas de detalle y activas: el balance no suma las cuentas mayores. */
class CuentasDeMovimientoTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesContabilidadFixtures;
    use CreatesCreditFixtures;
    use CreatesGastosFixtures;
    use RefreshDatabase;

    private const COMPROBANTES = '/api/v1/contabilidad/comprobantes';

    /** Códigos fuera de los prefijos de cartera (1305/2205/2335), que exigen tercero y documento cruce. */
    private function cuenta(string $codigo, array $overrides = []): PlanCuenta
    {
        return $this->crearCuenta(['codigo' => $codigo, ...$overrides]);
    }

    private function payloadManual(PlanCuenta $debito, PlanCuenta $credito): array
    {
        return [
            'tipo_comprobante_id' => $this->crearTipoComprobante()->id,
            'fecha' => '2026-01-15',
            'concepto' => 'Asiento manual',
            'lineas' => [
                ['cuenta_id' => $debito->id, 'debito' => 100, 'credito' => 0],
                ['cuenta_id' => $credito->id, 'debito' => 0, 'credito' => 100],
            ],
        ];
    }

    private function crearAutomatico(PlanCuenta $debito, PlanCuenta $credito): void
    {
        app(ComprobanteService::class)->crearComprobante(
            $this->crearTipoComprobante()->id,
            '2026-01-15',
            [
                ['cuenta_id' => $debito->id, 'debito' => 100, 'credito' => 0],
                ['cuenta_id' => $credito->id, 'debito' => 0, 'credito' => 100],
            ],
            'Asiento automático',
            'credito',
            1,
        );
    }

    public function test_un_asiento_manual_rechaza_una_cuenta_mayor(): void
    {
        $this->actingAsAdmin();
        $mayor = $this->cuenta('91', ['permite_movimiento' => false]);

        $this->postJson(self::COMPROBANTES, $this->payloadManual($mayor, $this->cuenta('910505')))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lineas.0.cuenta_id' => 'La cuenta 91 es una cuenta mayor']);

        $this->assertDatabaseCount('comprobantes', 0);
    }

    public function test_un_asiento_manual_rechaza_una_cuenta_inactiva(): void
    {
        $this->actingAsAdmin();
        $inactiva = $this->cuenta('910510', ['activa' => false]);

        $this->postJson(self::COMPROBANTES, $this->payloadManual($this->cuenta('910505'), $inactiva))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lineas.1.cuenta_id' => 'La cuenta 910510 está inactiva']);

        $this->assertDatabaseCount('comprobantes', 0);
    }

    public function test_un_asiento_manual_rechaza_una_cuenta_de_otra_empresa_sin_revelarla(): void
    {
        $this->actingAsAdmin();
        $ajena = $this->cuenta('910599');

        $this->actingAsAdmin();
        $respuesta = $this->postJson(self::COMPROBANTES, $this->payloadManual($this->cuenta('910505'), $ajena));

        $respuesta->assertStatus(422)->assertJsonValidationErrors('lineas.1.cuenta_id');
        $this->assertStringNotContainsString('910599', $respuesta->json('errors')['lineas.1.cuenta_id'][0]);
    }

    public function test_un_asiento_manual_con_cuentas_de_detalle_activas_se_crea(): void
    {
        $this->actingAsAdmin();

        $this->postJson(self::COMPROBANTES, $this->payloadManual($this->cuenta('910505'), $this->cuenta('910510')))
            ->assertCreated();

        $this->assertDatabaseCount('asientos_contables', 2);
    }

    public function test_un_asiento_automatico_rechaza_una_cuenta_mayor(): void
    {
        $this->actingAsAdmin();
        $mayor = $this->cuenta('91', ['permite_movimiento' => false]);

        try {
            $this->crearAutomatico($this->cuenta('910505'), $mayor);
            $this->fail('El asiento automático sobre una cuenta mayor debió rechazarse.');
        } catch (ValidationException $excepcion) {
            $this->assertStringContainsString('La cuenta 91 es una cuenta mayor', $excepcion->errors()['lineas'][0]);
        }

        $this->assertDatabaseCount('comprobantes', 0);
    }

    public function test_un_asiento_automatico_rechaza_una_cuenta_inactiva(): void
    {
        $this->actingAsAdmin();
        $inactiva = $this->cuenta('910510', ['activa' => false]);

        $this->expectException(ValidationException::class);

        $this->crearAutomatico($inactiva, $this->cuenta('910505'));
    }

    public function test_un_asiento_automatico_rechaza_una_cuenta_eliminada(): void
    {
        $this->actingAsAdmin();
        $eliminada = $this->cuenta('910510');
        $eliminada->delete();

        $this->expectException(ValidationException::class);

        $this->crearAutomatico($eliminada, $this->cuenta('910505'));
    }

    public function test_el_desembolso_falla_si_la_regla_de_desembolso_apunta_a_una_cuenta_mayor(): void
    {
        $this->actingAsAdmin();
        $this->crearRegla(EventoContable::DesembolsoCredito, $this->cuenta('91', ['permite_movimiento' => false]), $this->cuenta('910505'));
        $credito = $this->crearCredito($this->crearCliente(), ['fecha_desembolso' => '2026-01-15']);

        $this->expectException(ValidationException::class);

        app(ComprobanteService::class)->contabilizarDesembolsoCredito($credito);
    }

    public function test_no_contabiliza_un_borrador_si_su_cuenta_se_inactivo_despues(): void
    {
        $this->actingAsAdmin();
        $cuenta = $this->cuenta('910505');
        $id = $this->postJson(self::COMPROBANTES, $this->payloadManual($cuenta, $this->cuenta('910510')))->assertCreated()->json('comprobante.id');
        $this->postJson(self::COMPROBANTES."/{$id}/revisar")->assertOk();

        $cuenta->update(['activa' => false]);

        $this->postJson(self::COMPROBANTES."/{$id}/contabilizar")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lineas' => 'La cuenta 910505 está inactiva']);

        $this->assertDatabaseHas('comprobantes', ['id' => $id, 'estado' => 'revisado']);
    }

    public function test_el_reverso_procede_aunque_la_cuenta_ya_no_admita_movimiento(): void
    {
        $this->actingAsAdmin();
        $cuenta = $this->cuenta('910505');
        $otra = $this->cuenta('910510');
        $this->crearAutomatico($cuenta, $otra);
        $original = Comprobante::query()->firstOrFail();

        PlanCuenta::query()->whereKey($cuenta->id)->update(['activa' => false]);

        $reverso = app(ComprobanteService::class)->reversarComprobante($original, 'Corrección');

        $this->assertDatabaseHas('comprobantes', ['id' => $original->id, 'estado' => 'anulado']);
        $this->assertDatabaseHas('asientos_contables', ['comprobante_id' => $reverso->id, 'cuenta_id' => $cuenta->id]);
    }

    public function test_una_regla_de_contabilizacion_rechaza_una_cuenta_mayor(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/contabilidad/reglas', [
            'evento' => EventoContable::DesembolsoCredito->value,
            'cuenta_debito_id' => $this->cuenta('91', ['permite_movimiento' => false])->id,
            'cuenta_credito_id' => $this->cuenta('910505')->id,
            'tipo_comprobante_id' => $this->crearTipoComprobante()->id,
        ])->assertStatus(422)->assertJsonValidationErrors('cuenta_debito_id');

        $this->assertDatabaseCount('reglas_contabilizacion', 0);
    }

    public function test_una_parametrizacion_de_gasto_rechaza_una_cuenta_inactiva(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/gastos/parametrizaciones', [
            'tipo_gasto_id' => $this->crearTipoGasto()->id,
            'fecha_desde' => '2026-01-01',
            'tipo_comprobante_id' => $this->crearTipoComprobante()->id,
            'cuenta_gasto_id' => $this->cuenta('519505', ['activa' => false])->id,
            'cuenta_cxp_id' => $this->cuenta('233595')->id,
        ])->assertStatus(422)->assertJsonValidationErrors('cuenta_gasto_id');
    }

    public function test_no_se_desmarca_permite_movimiento_en_una_cuenta_con_movimientos(): void
    {
        $this->actingAsAdmin();
        $cuenta = $this->cuenta('910505');
        $this->crearAutomatico($cuenta, $this->cuenta('910510'));

        $this->patchJson("/api/v1/contabilidad/plan-cuentas/{$cuenta->id}", ['permite_movimiento' => false])
            ->assertStatus(422)
            ->assertJsonValidationErrors('permite_movimiento');

        $this->assertDatabaseHas('plan_cuentas', ['id' => $cuenta->id, 'permite_movimiento' => true]);
    }

    public function test_se_desmarca_permite_movimiento_en_una_cuenta_sin_movimientos(): void
    {
        $this->actingAsAdmin();
        $cuenta = $this->cuenta('91');

        $this->patchJson("/api/v1/contabilidad/plan-cuentas/{$cuenta->id}", ['permite_movimiento' => false])->assertOk();

        $this->assertDatabaseHas('plan_cuentas', ['id' => $cuenta->id, 'permite_movimiento' => false]);
    }

    public function test_una_cuenta_con_movimientos_puede_seguir_editandose_sin_volverse_mayor(): void
    {
        $this->actingAsAdmin();
        $cuenta = $this->cuenta('910505');
        $this->crearAutomatico($cuenta, $this->cuenta('910510'));

        $this->patchJson("/api/v1/contabilidad/plan-cuentas/{$cuenta->id}", ['permite_movimiento' => true, 'nombre' => 'Renombrada'])->assertOk();

        $this->assertDatabaseHas('plan_cuentas', ['id' => $cuenta->id, 'nombre' => 'Renombrada']);
    }
}
