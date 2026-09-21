<?php

namespace Tests\Feature\Creditos;

use App\Clientes\Models\Cliente;
use App\Creditos\Enums\EstadoCredito;
use App\Creditos\Enums\EstadoCuota;
use App\Creditos\Enums\TipoCredito;
use App\Creditos\Models\Credito;
use App\Creditos\Services\CuotaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesCreditFixtures;
use Tests\TestCase;

class RefinanciacionTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesCreditFixtures;
    use RefreshDatabase;

    private Cliente $cliente;

    /** Origen desembolsado con una cuota pagada y otra pendiente. */
    private function crearOrigen(): Credito
    {
        $this->actingAsAdmin();
        $this->cliente = $this->crearCliente();
        $origen = $this->crearCredito($this->cliente);
        $this->crearCuota($origen, ['numero_cuota' => 1, 'estado' => EstadoCuota::Pagada, 'valor' => 300, 'valor_pagado' => 300]);
        $this->crearCuota($origen, ['numero_cuota' => 2, 'estado' => EstadoCuota::Pendiente, 'valor' => 300, 'valor_pagado' => 0, 'fecha_vencimiento' => '2026-03-01']);

        return $origen;
    }

    private function solicitarRefinanciacion(Credito $origen, ?Cliente $cliente = null): TestResponse
    {
        return $this->postJson('/api/v1/creditos', [
            'cliente_id' => ($cliente ?? $this->cliente)->id,
            'tipo_credito' => TipoCredito::Refinanciacion->value,
            'credito_origen_id' => $origen->id,
            'valor_prestamo' => 500,
            'tasa_interes' => 5,
            'plazo' => 3,
            'tipo_pago' => 'mensual',
            'fecha_desembolso' => '2026-04-01',
        ]);
    }

    /** Solicita y aprueba la refinanciación, sin desembolsarla todavía; devuelve su id. */
    private function aprobarRefinanciacion(Credito $origen): int
    {
        $id = $this->solicitarRefinanciacion($origen)->assertCreated()->json('credito.id');
        $this->postJson("/api/v1/creditos/{$id}/aprobar")->assertOk();

        return $id;
    }

    /** Origen ya refinanciado: solicitud, aprobación y desembolso de la refinanciación. */
    private function refinanciar(): Credito
    {
        $origen = $this->crearOrigen();
        $id = $this->aprobarRefinanciacion($origen);
        $this->postJson("/api/v1/creditos/{$id}/desembolsar")->assertOk();

        return $origen->fresh();
    }

    private function refinanciacionDe(Credito $origen): Credito
    {
        return Credito::where('credito_origen_id', $origen->id)->firstOrFail();
    }

    public function test_al_solicitar_la_refinanciacion_el_original_sigue_desembolsado_y_recibe_pagos(): void
    {
        $origen = $this->crearOrigen();

        $this->solicitarRefinanciacion($origen)->assertCreated();

        $this->assertSame(EstadoCredito::Desembolsado, $origen->fresh()->estado);
        $this->postJson("/api/v1/creditos/{$origen->id}/pagos", ['valor_pago' => 100, 'forma_pago' => 'efectivo'])->assertCreated();
    }

    public function test_al_desembolsar_la_refinanciacion_el_original_queda_refinanciado_y_su_historial_intacto(): void
    {
        $origen = $this->refinanciar();

        $this->assertSame(EstadoCredito::Refinanciado, $origen->estado);
        $this->assertSame(EstadoCredito::Desembolsado, $this->refinanciacionDe($origen)->estado);
        $this->assertSame(['pagada', 'pendiente'], $origen->cuotas()->orderBy('numero_cuota')->get()->map(fn ($cuota) => $cuota->estado->value)->all());
        $this->assertSame('0.00', $origen->cuotas()->where('numero_cuota', 2)->first()->valor_pagado->aDecimalString());
    }

    public function test_si_el_original_deja_de_estar_desembolsado_no_se_puede_desembolsar_la_refinanciacion(): void
    {
        $origen = $this->crearOrigen();
        $id = $this->aprobarRefinanciacion($origen);
        $origen->update(['estado' => EstadoCredito::Pagado]);

        $this->postJson("/api/v1/creditos/{$id}/desembolsar")
            ->assertStatus(422)
            ->assertJsonPath('errors.credito_origen_id.0', 'El crédito original ya no está desembolsado; no se puede desembolsar esta refinanciación.');

        $refinanciacion = Credito::findOrFail($id);
        $this->assertSame(EstadoCredito::Aprobado, $refinanciacion->estado);
        $this->assertNull($refinanciacion->desembolsado_en);
        $this->assertSame(EstadoCredito::Pagado, $origen->fresh()->estado);
    }

    public function test_una_refinanciacion_solicitada_o_aprobada_se_cancela_sin_tocar_al_original(): void
    {
        $origen = $this->crearOrigen();

        $solicitada = $this->solicitarRefinanciacion($origen)->assertCreated()->json('credito.id');
        $this->postJson("/api/v1/creditos/{$solicitada}/cancelar")->assertOk();

        $aprobada = $this->aprobarRefinanciacion($origen);
        $this->postJson("/api/v1/creditos/{$aprobada}/cancelar")->assertOk();

        $this->assertSame(EstadoCredito::Desembolsado, $origen->fresh()->estado);
    }

    public function test_no_se_puede_cancelar_ni_eliminar_una_refinanciacion_desembolsada(): void
    {
        $origen = $this->refinanciar();
        $refinanciacion = $this->refinanciacionDe($origen);

        $this->postJson("/api/v1/creditos/{$refinanciacion->id}/cancelar")
            ->assertStatus(422)
            ->assertJsonPath('errors.credito.0', 'No se puede cancelar ni eliminar una refinanciación desembolsada: el crédito original ya quedó congelado.');
        $this->deleteJson("/api/v1/creditos/{$refinanciacion->id}")->assertStatus(422)->assertJsonValidationErrors('credito');

        $this->assertSame(EstadoCredito::Desembolsado, $refinanciacion->fresh()->estado);
        $this->assertSame(EstadoCredito::Refinanciado, $origen->fresh()->estado);
    }

    public function test_no_se_puede_pedir_otra_refinanciacion_mientras_hay_una_en_curso(): void
    {
        $origen = $this->crearOrigen();
        $primera = $this->solicitarRefinanciacion($origen)->assertCreated()->json('credito.id');

        $this->solicitarRefinanciacion($origen)
            ->assertStatus(422)
            ->assertJsonPath('errors.credito_origen_id.0', 'Este crédito ya tiene una refinanciación en curso (solicitada o aprobada).');

        $this->postJson("/api/v1/creditos/{$primera}/cancelar")->assertOk();
        $this->solicitarRefinanciacion($origen)->assertCreated();
    }

    public function test_un_credito_refinanciado_no_recibe_pagos(): void
    {
        $origen = $this->refinanciar();

        $respuesta = $this->postJson("/api/v1/creditos/{$origen->id}/pagos", ['valor_pago' => 100, 'forma_pago' => 'efectivo']);

        $respuesta->assertStatus(422)->assertJsonValidationErrors('estado');
        $respuesta->assertJsonPath('errors.estado.0', 'Este crédito fue refinanciado y quedó congelado; los pagos se aplican al crédito que lo reemplazó.');
        $this->assertDatabaseCount('pagos', 0);
    }

    public function test_un_credito_refinanciado_no_se_puede_liquidar(): void
    {
        $origen = $this->refinanciar();

        $this->postJson("/api/v1/creditos/{$origen->id}/liquidacion", ['forma_pago' => 'efectivo'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('estado');

        $this->assertDatabaseCount('pagos', 0);
        $this->assertSame(EstadoCredito::Refinanciado, $origen->fresh()->estado);
    }

    public function test_un_credito_refinanciado_no_se_puede_cancelar_ni_eliminar(): void
    {
        $origen = $this->refinanciar();

        $this->postJson("/api/v1/creditos/{$origen->id}/cancelar")->assertStatus(422)->assertJsonValidationErrors('estado');
        $this->deleteJson("/api/v1/creditos/{$origen->id}")->assertStatus(422)->assertJsonValidationErrors('credito');

        $this->assertSame(EstadoCredito::Refinanciado, $origen->fresh()->estado);
        $this->assertSame(2, $origen->cuotas()->count());
    }

    public function test_el_detalle_de_un_credito_refinanciado_no_lo_muestra_en_mora(): void
    {
        $origen = $this->refinanciar();
        $origen->cuotas()->where('numero_cuota', 2)->update(['estado' => EstadoCuota::Vencida]);

        $this->getJson("/api/v1/creditos/{$origen->id}")
            ->assertOk()
            ->assertJsonPath('cartera', 'Refinanciado')
            ->assertJsonPath('credito.estado', 'refinanciado');
    }

    public function test_solo_se_puede_refinanciar_un_credito_desembolsado(): void
    {
        $this->actingAsAdmin();
        $cliente = $this->crearCliente();

        foreach ([EstadoCredito::Solicitado, EstadoCredito::Aprobado, EstadoCredito::Pagado, EstadoCredito::Cancelado, EstadoCredito::Refinanciado] as $estado) {
            $origen = $this->crearCredito($cliente, ['estado' => $estado]);

            $this->solicitarRefinanciacion($origen, $cliente)
                ->assertStatus(422)
                ->assertJsonPath('errors.credito_origen_id.0', 'Solo se puede refinanciar un crédito desembolsado.');

            $this->assertSame($estado, $origen->fresh()->estado, "El origen {$estado->value} no debe cambiar de estado.");
        }

        $this->assertDatabaseCount('creditos', 5);
    }

    public function test_la_mora_solo_marca_cuotas_de_creditos_desembolsados(): void
    {
        $this->actingAsAdmin();
        $cliente = $this->crearCliente();
        $vencida = now()->subDays(20)->toDateString();
        $cuotaPorEstado = [];

        foreach ([EstadoCredito::Desembolsado, EstadoCredito::Solicitado, EstadoCredito::Cancelado, EstadoCredito::Refinanciado] as $estado) {
            $credito = $this->crearCredito($cliente, ['estado' => $estado]);
            $cuotaPorEstado[$estado->value] = $this->crearCuota($credito, ['fecha_vencimiento' => $vencida]);
        }

        app(CuotaService::class)->actualizarVencidas();

        $this->assertSame(EstadoCuota::Vencida, $cuotaPorEstado['desembolsado']->fresh()->estado);

        foreach (['solicitado', 'cancelado', 'refinanciado'] as $estado) {
            $this->assertSame(EstadoCuota::Pendiente, $cuotaPorEstado[$estado]->fresh()->estado, "Un crédito {$estado} no entra en mora.");
        }
    }

    public function test_un_credito_refinanciado_no_aparece_en_la_cartera(): void
    {
        $origen = $this->refinanciar();
        $origen->cuotas()->where('numero_cuota', 2)->update(['fecha_vencimiento' => now()->subDays(40)->toDateString()]);

        $respuesta = $this->getJson('/api/v1/cartera')->assertOk();

        $creditosEnCartera = collect($respuesta->json('al_dia'))->merge($respuesta->json('en_mora'))->pluck('credito.id');
        $this->assertFalse($creditosEnCartera->contains($origen->id), 'El original refinanciado no cuenta en la cartera.');
        $this->assertTrue($creditosEnCartera->contains($this->refinanciacionDe($origen)->id), 'La refinanciación desembolsada sí.');
    }

    public function test_un_credito_refinanciado_ya_no_bloquea_la_baja_del_cliente(): void
    {
        $this->actingAsAdmin();
        $cliente = $this->crearCliente();
        $this->crearCredito($cliente, ['estado' => EstadoCredito::Refinanciado]);
        $this->crearCredito($cliente, ['estado' => EstadoCredito::Pagado]);
        $conActivo = $this->crearCliente();
        $this->crearCredito($conActivo, ['estado' => EstadoCredito::Refinanciado]);
        $this->crearCredito($conActivo, ['estado' => EstadoCredito::Desembolsado]);

        $this->deleteJson("/api/v1/clientes/{$conActivo->id}")->assertStatus(422)->assertJsonValidationErrors('cliente');
        $this->deleteJson("/api/v1/clientes/{$cliente->id}")->assertNoContent();
    }

    public function test_no_se_puede_refinanciar_un_credito_de_otra_empresa(): void
    {
        $this->actingAsAdmin($this->crearEmpresaDePrueba());
        $ajeno = $this->crearCredito($this->crearCliente());

        $this->actingAsAdmin();
        $cliente = $this->crearCliente();

        $this->solicitarRefinanciacion($ajeno, $cliente)->assertStatus(422)->assertJsonValidationErrors('credito_origen_id');

        $this->assertSame(EstadoCredito::Desembolsado, $ajeno->fresh()->estado);
    }
}
