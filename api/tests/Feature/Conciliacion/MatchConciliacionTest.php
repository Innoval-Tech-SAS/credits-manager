<?php

namespace Tests\Feature\Conciliacion;

use App\Contabilidad\Models\AsientoContable;
use App\Contabilidad\Models\Comprobante;
use App\Contabilidad\Models\PlanCuenta;
use App\Contabilidad\Services\ComprobanteService;
use App\Core\Dinero\Dinero;
use App\Empresas\Models\Empresa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesConciliacionFixtures;
use Tests\TestCase;

class MatchConciliacionTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesConciliacionFixtures;
    use RefreshDatabase;

    private function asientoEnCuenta(PlanCuenta $cuenta, string $valor, string $fecha = '2026-10-05', bool $comoDebito = true): AsientoContable
    {
        $contraCuenta = $this->crearCuenta();
        $tipo = $this->crearTipoComprobante();
        $monto = Dinero::desdePesos($valor);

        $lineas = $comoDebito
            ? [['cuenta_id' => $cuenta->id, 'debito' => $monto, 'credito' => 0], ['cuenta_id' => $contraCuenta->id, 'debito' => 0, 'credito' => $monto]]
            : [['cuenta_id' => $cuenta->id, 'debito' => 0, 'credito' => $monto], ['cuenta_id' => $contraCuenta->id, 'debito' => $monto, 'credito' => 0]];

        $comprobante = app(ComprobanteService::class)->crearComprobante($tipo->id, $fecha, $lineas, 'Asiento de prueba');

        return AsientoContable::where('comprobante_id', $comprobante->id)->where('cuenta_id', $cuenta->id)->firstOrFail();
    }

    public function test_sin_autenticar_recibe_401(): void
    {
        $this->getJson('/api/v1/conciliacion/extractos/1/candidatos')->assertStatus(401);
        $this->postJson('/api/v1/conciliacion/movimientos/1/conciliar', [])->assertStatus(401);
        $this->postJson('/api/v1/conciliacion/movimientos/1/enviar-a-puente', [])->assertStatus(401);
        $this->postJson('/api/v1/conciliacion/marcas/1/desmarcar', [])->assertStatus(401);
    }

    public function test_asesor_sin_permiso_recibe_403(): void
    {
        $admin = $this->actingAsAdmin();
        $cuenta = $this->crearCuentaBancaria();
        $extracto = $this->crearExtractoBancario($cuenta);
        $movimiento = $this->crearMovimientoExtracto($extracto);

        $this->actingAsAsesor(Empresa::findOrFail($admin->empresa_id));

        $this->getJson("/api/v1/conciliacion/extractos/{$extracto->id}/candidatos")->assertStatus(403);
        $this->postJson("/api/v1/conciliacion/movimientos/{$movimiento->id}/conciliar", ['asiento_contable_id' => 1])->assertStatus(403);
    }

    public function test_candidatos_de_otra_empresa_responde_404(): void
    {
        $this->actingAsAdmin();
        $cuenta = $this->crearCuentaBancaria();
        $extracto = $this->crearExtractoBancario($cuenta);

        $this->actingAsAdmin();

        $this->getJson("/api/v1/conciliacion/extractos/{$extracto->id}/candidatos")->assertStatus(404);
    }

    public function test_candidatos_clasifica_exacto_duplicado_y_sin_candidato(): void
    {
        $this->actingAsAdmin();
        $cuenta = $this->crearCuentaBancaria();
        $extracto = $this->crearExtractoBancario($cuenta);

        $movimientoExacto = $this->crearMovimientoExtracto($extracto, ['descripcion' => 'Exacto', 'valor' => '1000', 'saldo' => '1000']);
        $movimientoDuplicado = $this->crearMovimientoExtracto($extracto, ['descripcion' => 'Duplicado', 'valor' => '2000', 'saldo' => '2000']);
        $movimientoSinCandidato = $this->crearMovimientoExtracto($extracto, ['descripcion' => 'Sin candidato', 'fecha' => '2026-01-01', 'valor' => '9999', 'saldo' => '9999']);

        $this->asientoEnCuenta($cuenta->planCuenta, '1000');
        $this->asientoEnCuenta($cuenta->planCuenta, '2000');
        $this->asientoEnCuenta($cuenta->planCuenta, '2000');

        $respuesta = $this->getJson("/api/v1/conciliacion/extractos/{$extracto->id}/candidatos")->assertOk();

        $sugerencias = collect($respuesta->json('sugerencias'))->keyBy('movimiento_extracto_id');
        $this->assertSame('exacto', $sugerencias[$movimientoExacto->id]['estado_sugerido']);
        $this->assertCount(1, $sugerencias[$movimientoExacto->id]['sugeridos']);
        $this->assertSame('duplicado', $sugerencias[$movimientoDuplicado->id]['estado_sugerido']);
        $this->assertCount(2, $sugerencias[$movimientoDuplicado->id]['sugeridos']);
        $this->assertSame('sin_candidato', $sugerencias[$movimientoSinCandidato->id]['estado_sugerido']);
    }

    public function test_candidatos_incluye_movimientos_ya_resueltos_con_su_marca_vigente(): void
    {
        $this->actingAsAdmin();
        $cuenta = $this->crearCuentaBancaria();
        $extracto = $this->crearExtractoBancario($cuenta);
        $movimiento = $this->crearMovimientoExtracto($extracto, ['valor' => '1000']);
        $asiento = $this->asientoEnCuenta($cuenta->planCuenta, '1000');

        $marca = $this->postJson("/api/v1/conciliacion/movimientos/{$movimiento->id}/conciliar", ['asiento_contable_id' => $asiento->id])
            ->assertStatus(201)->json('marca');

        $respuesta = $this->getJson("/api/v1/conciliacion/extractos/{$extracto->id}/candidatos")->assertOk();

        $movimientos = collect($respuesta->json('movimientos'))->keyBy('id');
        $this->assertSame('conciliado', $movimientos[$movimiento->id]['estado']);
        $this->assertSame($marca['id'], $movimientos[$movimiento->id]['marca_vigente']['id']);
        $this->assertEmpty($respuesta->json('sugerencias'));
    }

    public function test_conciliar_marca_el_movimiento_y_lo_deja_conciliado(): void
    {
        $this->actingAsAdmin();
        $cuenta = $this->crearCuentaBancaria();
        $extracto = $this->crearExtractoBancario($cuenta);
        $movimiento = $this->crearMovimientoExtracto($extracto, ['valor' => '1000']);
        $asiento = $this->asientoEnCuenta($cuenta->planCuenta, '1000');

        $respuesta = $this->postJson("/api/v1/conciliacion/movimientos/{$movimiento->id}/conciliar", ['asiento_contable_id' => $asiento->id])
            ->assertStatus(201);

        $this->assertSame('conciliado', $respuesta->json('marca.tipo'));
        $this->assertDatabaseHas('movimientos_extracto', ['id' => $movimiento->id, 'estado' => 'conciliado']);
        $this->assertDatabaseHas('marcas_conciliacion', ['movimiento_extracto_id' => $movimiento->id, 'asiento_contable_id' => $asiento->id, 'tipo' => 'conciliado']);
    }

    public function test_conciliar_rechaza_valor_que_no_coincide(): void
    {
        $this->actingAsAdmin();
        $cuenta = $this->crearCuentaBancaria();
        $extracto = $this->crearExtractoBancario($cuenta);
        $movimiento = $this->crearMovimientoExtracto($extracto, ['valor' => '1000']);
        $asiento = $this->asientoEnCuenta($cuenta->planCuenta, '500');

        $this->postJson("/api/v1/conciliacion/movimientos/{$movimiento->id}/conciliar", ['asiento_contable_id' => $asiento->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('asiento_contable_id');
    }

    public function test_conciliar_rechaza_un_asiento_ya_conciliado(): void
    {
        $this->actingAsAdmin();
        $cuenta = $this->crearCuentaBancaria();
        $extracto = $this->crearExtractoBancario($cuenta);
        $movimientoUno = $this->crearMovimientoExtracto($extracto, ['valor' => '1000']);
        $movimientoDos = $this->crearMovimientoExtracto($extracto, ['valor' => '1000']);
        $asiento = $this->asientoEnCuenta($cuenta->planCuenta, '1000');

        $this->postJson("/api/v1/conciliacion/movimientos/{$movimientoUno->id}/conciliar", ['asiento_contable_id' => $asiento->id])->assertStatus(201);

        $this->postJson("/api/v1/conciliacion/movimientos/{$movimientoDos->id}/conciliar", ['asiento_contable_id' => $asiento->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('asiento_contable_id');
    }

    public function test_conciliar_un_movimiento_ya_conciliado_responde_422(): void
    {
        $this->actingAsAdmin();
        $cuenta = $this->crearCuentaBancaria();
        $extracto = $this->crearExtractoBancario($cuenta);
        $movimiento = $this->crearMovimientoExtracto($extracto, ['valor' => '1000']);
        $asientoUno = $this->asientoEnCuenta($cuenta->planCuenta, '1000');
        $asientoDos = $this->asientoEnCuenta($cuenta->planCuenta, '1000');

        $this->postJson("/api/v1/conciliacion/movimientos/{$movimiento->id}/conciliar", ['asiento_contable_id' => $asientoUno->id])->assertStatus(201);

        $this->postJson("/api/v1/conciliacion/movimientos/{$movimiento->id}/conciliar", ['asiento_contable_id' => $asientoDos->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('movimiento');
    }

    public function test_conciliar_un_asiento_de_otra_empresa_responde_422(): void
    {
        $admin = $this->actingAsAdmin();
        $cuenta = $this->crearCuentaBancaria();
        $extracto = $this->crearExtractoBancario($cuenta);
        $movimiento = $this->crearMovimientoExtracto($extracto, ['valor' => '1000']);

        $this->actingAsAdmin();
        $asientoAjeno = $this->asientoEnCuenta($this->crearCuenta(), '1000');

        $this->actingAs($admin, 'sanctum');

        $this->postJson("/api/v1/conciliacion/movimientos/{$movimiento->id}/conciliar", ['asiento_contable_id' => $asientoAjeno->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('asiento_contable_id');
    }

    public function test_enviar_a_puente_sin_cuenta_puente_configurada_responde_422(): void
    {
        $this->actingAsAdmin();
        $cuenta = $this->crearCuentaBancaria();
        $extracto = $this->crearExtractoBancario($cuenta);
        $movimiento = $this->crearMovimientoExtracto($extracto, ['valor' => '1000']);
        $tipo = $this->crearTipoComprobante();

        $this->postJson("/api/v1/conciliacion/movimientos/{$movimiento->id}/enviar-a-puente", ['tipo_comprobante_id' => $tipo->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('movimiento');
    }

    public function test_enviar_a_puente_genera_comprobante_y_marca_el_movimiento(): void
    {
        $this->actingAsAdmin();
        $cuentaPuente = $this->crearCuenta();
        $cuenta = $this->crearCuentaBancaria(['plan_cuenta_puente_id' => $cuentaPuente->id]);
        $extracto = $this->crearExtractoBancario($cuenta);
        $movimiento = $this->crearMovimientoExtracto($extracto, ['valor' => '1000']);
        $tipo = $this->crearTipoComprobante();

        $respuesta = $this->postJson("/api/v1/conciliacion/movimientos/{$movimiento->id}/enviar-a-puente", [
            'tipo_comprobante_id' => $tipo->id,
            'observacion' => 'Sin origen identificado',
        ])->assertStatus(201);

        $this->assertSame('puente', $respuesta->json('marca.tipo'));
        $this->assertDatabaseHas('movimientos_extracto', ['id' => $movimiento->id, 'estado' => 'puente']);

        $comprobanteId = $respuesta->json('marca.comprobante_generado_id');
        $this->assertNotNull($comprobanteId);
        $comprobante = Comprobante::findOrFail($comprobanteId);
        $this->assertTrue($comprobante->total_debito->igualA($comprobante->total_credito));
        $this->assertDatabaseHas('asientos_contables', ['comprobante_id' => $comprobanteId, 'cuenta_id' => $cuentaPuente->id]);
    }

    public function test_desmarcar_una_marca_conciliado_vuelve_el_movimiento_a_pendiente(): void
    {
        $this->actingAsAdmin();
        $cuenta = $this->crearCuentaBancaria();
        $extracto = $this->crearExtractoBancario($cuenta);
        $movimiento = $this->crearMovimientoExtracto($extracto, ['valor' => '1000']);
        $asiento = $this->asientoEnCuenta($cuenta->planCuenta, '1000');

        $marca = $this->postJson("/api/v1/conciliacion/movimientos/{$movimiento->id}/conciliar", ['asiento_contable_id' => $asiento->id])
            ->assertStatus(201)->json('marca');

        $this->postJson("/api/v1/conciliacion/marcas/{$marca['id']}/desmarcar", ['motivo' => 'Cruce equivocado'])->assertOk();

        $this->assertDatabaseHas('movimientos_extracto', ['id' => $movimiento->id, 'estado' => 'pendiente']);
        $this->assertDatabaseHas('marcas_conciliacion', ['marca_origen_id' => $marca['id'], 'tipo' => 'reversa']);

        // Ya pendiente de nuevo: se puede volver a conciliar con otro asiento.
        $otroAsiento = $this->asientoEnCuenta($cuenta->planCuenta, '1000');
        $this->postJson("/api/v1/conciliacion/movimientos/{$movimiento->id}/conciliar", ['asiento_contable_id' => $otroAsiento->id])->assertStatus(201);
    }

    public function test_desmarcar_una_marca_puente_reversa_el_comprobante_generado(): void
    {
        $this->actingAsAdmin();
        $cuentaPuente = $this->crearCuenta();
        $cuenta = $this->crearCuentaBancaria(['plan_cuenta_puente_id' => $cuentaPuente->id]);
        $extracto = $this->crearExtractoBancario($cuenta);
        $movimiento = $this->crearMovimientoExtracto($extracto, ['valor' => '1000']);
        $tipo = $this->crearTipoComprobante();

        $marca = $this->postJson("/api/v1/conciliacion/movimientos/{$movimiento->id}/enviar-a-puente", ['tipo_comprobante_id' => $tipo->id])
            ->assertStatus(201)->json('marca');

        $this->postJson("/api/v1/conciliacion/marcas/{$marca['id']}/desmarcar", ['motivo' => 'Se identificó el origen'])->assertOk();

        $this->assertDatabaseHas('movimientos_extracto', ['id' => $movimiento->id, 'estado' => 'pendiente']);

        $original = Comprobante::findOrFail($marca['comprobante_generado_id']);
        $this->assertSame('anulado', $original->estado->value);
        $this->assertNotNull($original->comprobante_reverso_id);
    }

    public function test_desmarcar_una_marca_ya_reversada_responde_422(): void
    {
        $this->actingAsAdmin();
        $cuenta = $this->crearCuentaBancaria();
        $extracto = $this->crearExtractoBancario($cuenta);
        $movimiento = $this->crearMovimientoExtracto($extracto, ['valor' => '1000']);
        $asiento = $this->asientoEnCuenta($cuenta->planCuenta, '1000');

        $marca = $this->postJson("/api/v1/conciliacion/movimientos/{$movimiento->id}/conciliar", ['asiento_contable_id' => $asiento->id])
            ->assertStatus(201)->json('marca');

        $this->postJson("/api/v1/conciliacion/marcas/{$marca['id']}/desmarcar", ['motivo' => 'Primera reversa'])->assertOk();

        $this->postJson("/api/v1/conciliacion/marcas/{$marca['id']}/desmarcar", ['motivo' => 'Segunda reversa'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('marca');
    }

    public function test_un_movimiento_o_una_marca_de_otra_empresa_responde_404(): void
    {
        $this->actingAsAdmin();
        $cuenta = $this->crearCuentaBancaria();
        $extracto = $this->crearExtractoBancario($cuenta);
        $movimiento = $this->crearMovimientoExtracto($extracto, ['valor' => '1000']);
        $asiento = $this->asientoEnCuenta($cuenta->planCuenta, '1000');
        $marca = $this->postJson("/api/v1/conciliacion/movimientos/{$movimiento->id}/conciliar", ['asiento_contable_id' => $asiento->id])
            ->assertStatus(201)->json('marca');

        $this->actingAsAdmin();

        $this->postJson("/api/v1/conciliacion/movimientos/{$movimiento->id}/conciliar", ['asiento_contable_id' => $asiento->id])->assertStatus(404);
        $this->postJson("/api/v1/conciliacion/marcas/{$marca['id']}/desmarcar", ['motivo' => 'Intento ajeno'])->assertStatus(404);
    }
}
