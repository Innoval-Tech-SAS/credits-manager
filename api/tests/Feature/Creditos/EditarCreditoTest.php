<?php

namespace Tests\Feature\Creditos;

use App\Core\Dinero\Dinero;
use App\Creditos\Enums\EstadoCredito;
use App\Creditos\Enums\TipoCredito;
use App\Creditos\Models\Credito;
use App\Creditos\Models\Cuota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\AfirmaAuditoria;
use Tests\Concerns\CreatesAuditoriaFixtures;
use Tests\Concerns\CreatesCreditFixtures;
use Tests\TestCase;

class EditarCreditoTest extends TestCase
{
    use ActsAsAdmin;
    use AfirmaAuditoria;
    use CreatesAuditoriaFixtures;
    use CreatesCreditFixtures;
    use RefreshDatabase;

    private function creditoSolicitado(): Credito
    {
        $respuesta = $this->postJson('/api/v1/creditos', [
            'cliente_id' => $this->crearCliente()->id,
            'tipo_credito' => 'nuevo',
            'valor_prestamo' => 1000,
            'tasa_interes' => 2,
            'plazo' => 3,
            'tipo_pago' => 'mensual',
            'fecha_desembolso' => '2026-01-01',
        ])->assertCreated();

        return Credito::findOrFail($respuesta->json('credito.id'));
    }

    private function nuevasCondiciones(array $cambios = []): array
    {
        return array_merge([
            'valor_prestamo' => 1200,
            'tasa_interes' => 3,
            'plazo' => '6',
            'tipo_pago' => 'quincenal',
            'fecha_desembolso' => '2026-02-01',
            'motivo' => 'El cliente pidió un monto y plazo distintos',
        ], $cambios);
    }

    public function test_rechaza_una_edicion_sin_los_campos_obligatorios(): void
    {
        $this->actingAsAdmin();
        $credito = $this->creditoSolicitado();

        $this->patchJson("/api/v1/creditos/{$credito->id}", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['valor_prestamo', 'tasa_interes', 'plazo', 'tipo_pago', 'fecha_desembolso', 'motivo']);
    }

    public function test_el_motivo_es_obligatorio(): void
    {
        $this->actingAsAdmin();
        $credito = $this->creditoSolicitado();

        $this->patchJson("/api/v1/creditos/{$credito->id}", $this->nuevasCondiciones(['motivo' => '   ']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('motivo');
    }

    public function test_un_credito_nuevo_no_admite_valor_adicional(): void
    {
        $this->actingAsAdmin();
        $credito = $this->creditoSolicitado();

        $this->patchJson("/api/v1/creditos/{$credito->id}", $this->nuevasCondiciones(['valor_adicional' => 50]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('valor_adicional');
    }

    public function test_rechaza_editar_sin_autenticar(): void
    {
        $this->actingAsAdmin();
        $credito = $this->creditoSolicitado();
        $this->salirDeSesion();

        $this->patchJson("/api/v1/creditos/{$credito->id}", $this->nuevasCondiciones())->assertStatus(401);
    }

    public function test_un_asesor_sin_el_permiso_no_puede_editar(): void
    {
        $empresa = $this->crearEmpresaDePrueba();
        $this->actingAsAdmin($empresa);
        $credito = $this->creditoSolicitado();
        $this->actingAsAsesor($empresa);

        $this->patchJson("/api/v1/creditos/{$credito->id}", $this->nuevasCondiciones())->assertStatus(403);

        $this->assertDatabaseHas('creditos', ['id' => $credito->id, 'plazo' => 3]);
    }

    public function test_un_credito_inexistente_responde_404(): void
    {
        $this->actingAsAdmin();

        $this->patchJson('/api/v1/creditos/999999', $this->nuevasCondiciones())->assertStatus(404);
    }

    public function test_rechaza_editar_un_credito_ya_desembolsado(): void
    {
        $this->actingAsAdmin();
        $credito = $this->crearCredito($this->crearCliente(), ['estado' => EstadoCredito::Desembolsado]);

        $this->patchJson("/api/v1/creditos/{$credito->id}", $this->nuevasCondiciones())
            ->assertStatus(422)
            ->assertJsonValidationErrors('credito');

        $this->assertDatabaseHas('creditos', ['id' => $credito->id, 'plazo' => 3]);
    }

    public function test_edita_las_condiciones_y_regenera_el_plan_de_cuotas(): void
    {
        $this->actingAsAdmin();
        $credito = $this->creditoSolicitado();

        $respuesta = $this->patchJson("/api/v1/creditos/{$credito->id}", $this->nuevasCondiciones());

        $respuesta->assertOk()->assertJsonPath('credito.plazo', 6)->assertJsonPath('credito.valor_prestamo', '1200.00');

        $credito->refresh();
        $this->assertSame(120000, $credito->valor_prestamo->centavos());
        $this->assertSame('quincenal', $credito->tipo_pago->value);
        $this->assertSame(EstadoCredito::Solicitado, $credito->estado);

        $vigentes = Cuota::where('credito_id', $credito->id)->get();
        $this->assertCount(6, $vigentes);
        $this->assertSame(120000, $vigentes->sum(fn (Cuota $cuota) => $cuota->valor_capital->centavos()));
        $this->assertSame($credito->valor_total->centavos(), $vigentes->sum(fn (Cuota $cuota) => $cuota->valor->centavos()));
        $this->assertSame(9, Cuota::withTrashed()->where('credito_id', $credito->id)->count());
    }

    public function test_conserva_en_la_bitacora_valores_anteriores_y_nuevos_con_el_motivo(): void
    {
        $this->actingAsAdmin();
        $credito = $this->creditoSolicitado();

        $this->patchJson("/api/v1/creditos/{$credito->id}", $this->nuevasCondiciones())->assertOk();

        $evento = $this->assertEventoAuditoria('modificar', 'creditos', 'credito', $credito->id);

        $this->assertSame('El cliente pidió un monto y plazo distintos', $evento['motivo']);
        $this->assertSame(3, $evento['valores_anteriores']['plazo']);
        $this->assertSame(6, $evento['valores_nuevos']['plazo']);
        $this->assertSame('1000.00', $evento['valores_anteriores']['valor_prestamo']);
        $this->assertSame('1200.00', $evento['valores_nuevos']['valor_prestamo']);
        $this->assertSame(3, $evento['valores_nuevos']['cuotas_reemplazadas']);
        $this->assertSame(6, $evento['valores_nuevos']['cuotas_generadas']);
    }

    public function test_un_credito_aprobado_tambien_se_puede_editar(): void
    {
        $this->actingAsAdmin();
        $credito = $this->creditoSolicitado();
        $this->postJson("/api/v1/creditos/{$credito->id}/aprobar")->assertOk();

        $this->patchJson("/api/v1/creditos/{$credito->id}", $this->nuevasCondiciones())->assertOk();

        $this->assertDatabaseHas('creditos', ['id' => $credito->id, 'estado' => 'aprobado', 'plazo' => 6]);
    }

    public function test_un_usuario_con_el_permiso_individual_puede_editar(): void
    {
        $empresa = $this->crearEmpresaDePrueba();
        $this->actingAsAdmin($empresa);
        $credito = $this->creditoSolicitado();
        $this->actingAsRolConPermisos('editor_creditos', ['creditos.editar'], $empresa);

        $this->patchJson("/api/v1/creditos/{$credito->id}", $this->nuevasCondiciones())->assertOk();
    }

    public function test_en_una_refinanciacion_el_prestamo_es_el_saldo_tomado_mas_el_valor_adicional(): void
    {
        $this->actingAsAdmin();
        $cliente = $this->crearCliente();
        $origen = $this->crearCredito($cliente);
        $refinanciacion = $this->crearCredito($cliente, [
            'tipo_credito' => TipoCredito::Refinanciacion,
            'credito_origen_id' => $origen->id,
            'saldo_refinanciado' => Dinero::desdePesos(400),
            'valor_prestamo' => 500,
            'estado' => EstadoCredito::Solicitado,
        ]);

        $this->patchJson("/api/v1/creditos/{$refinanciacion->id}", $this->nuevasCondiciones(['valor_prestamo' => 900]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('valor_prestamo');

        $datos = $this->nuevasCondiciones(['valor_adicional' => 250]);
        unset($datos['valor_prestamo']);

        $this->patchJson("/api/v1/creditos/{$refinanciacion->id}", $datos)->assertOk();

        $refinanciacion->refresh();
        $this->assertSame(65000, $refinanciacion->valor_prestamo->centavos());
        $this->assertSame(40000, $refinanciacion->saldo_refinanciado->centavos());
    }
}
