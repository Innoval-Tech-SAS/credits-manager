<?php

namespace Tests\Feature\Facturacion;

use App\Creditos\Enums\EstadoCuota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesCreditFixtures;
use Tests\Concerns\CreatesFacturacionFixtures;
use Tests\TestCase;

class ConceptosSugeridosFacturacionTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesCreditFixtures;
    use CreatesFacturacionFixtures;
    use RefreshDatabase;

    private const BASE = '/api/v1/facturacion/documentos/conceptos-sugeridos';

    public function test_sin_autenticar_recibe_401(): void
    {
        $this->getJson(self::BASE.'?credito_id=1')->assertStatus(401);
    }

    public function test_sugiere_solo_el_interes_de_la_primera_cuota_pendiente_o_vencida(): void
    {
        $this->actingAsAdmin();
        $credito = $this->crearCredito($this->crearCliente());
        $cuotaVencida = $this->crearCuota($credito, [
            'numero_cuota' => 1,
            'fecha_vencimiento' => '2026-02-01',
            'valor_capital' => 250, 'valor_interes' => 30, 'valor_otros_gastos' => 5,
            'estado' => EstadoCuota::Vencida,
        ]);
        // Segunda cuota pendiente: no debe sugerir nada de ella, solo de la más antigua sin pagar.
        $this->crearCuota($credito, [
            'numero_cuota' => 2,
            'fecha_vencimiento' => '2026-03-01',
            'valor_capital' => 260, 'valor_interes' => 20, 'valor_otros_gastos' => 0,
            'estado' => EstadoCuota::Pendiente,
        ]);
        // Pagada: ya no cuenta como "primera pendiente" aunque fuera anterior en número.
        $this->crearCuota($credito, [
            'numero_cuota' => 3,
            'fecha_vencimiento' => '2026-04-01',
            'valor_capital' => 270, 'valor_interes' => 10, 'valor_otros_gastos' => 0,
            'estado' => EstadoCuota::Pagada,
        ]);

        $conceptos = $this->getJson(self::BASE.'?credito_id='.$credito->id)->assertOk()->json('conceptos');

        // Solo el interés corriente de la cuota 1 — ni su capital/otros gastos, ni nada de la cuota 2.
        $this->assertCount(1, $conceptos);
        $this->assertSame('interes_corriente', $conceptos[0]['tipo_concepto']);
        $this->assertSame($cuotaVencida->id, $conceptos[0]['cuota_id']);
        $this->assertSame('30.00', $conceptos[0]['valor']);
        $this->assertSame('2026-02-01', $conceptos[0]['fecha_vencimiento']);
    }

    public function test_no_sugiere_nada_si_el_interes_de_la_primera_cuota_pendiente_es_cero(): void
    {
        $this->actingAsAdmin();
        $credito = $this->crearCredito($this->crearCliente());
        $this->crearCuota($credito, [
            'numero_cuota' => 1, 'fecha_vencimiento' => '2026-02-01',
            'valor_capital' => 250, 'valor_interes' => 0, 'valor_otros_gastos' => 0,
            'estado' => EstadoCuota::Pendiente,
        ]);

        $conceptos = $this->getJson(self::BASE.'?credito_id='.$credito->id)->assertOk()->json('conceptos');

        $this->assertSame([], $conceptos);
    }

    public function test_un_credito_de_otra_empresa_responde_422(): void
    {
        $this->actingAsAdmin();
        $creditoAjeno = $this->crearCredito($this->crearCliente());

        $this->actingAsAdmin();

        $this->getJson(self::BASE.'?credito_id='.$creditoAjeno->id)->assertStatus(422)->assertJsonValidationErrors('credito_id');
    }

    public function test_excluye_el_interes_ya_facturado_en_una_factura_emitida(): void
    {
        $this->actingAsAdmin();
        $this->crearReglasFacturacion();
        $credito = $this->crearCredito($this->crearCliente());
        $cuota = $this->crearCuota($credito, [
            'numero_cuota' => 1, 'fecha_vencimiento' => '2026-02-01',
            'valor_capital' => 250, 'valor_interes' => 30, 'valor_otros_gastos' => 0,
            'estado' => EstadoCuota::Pendiente,
        ]);

        $borrador = $this->postJson('/api/v1/facturacion/documentos', [
            'cliente_id' => $credito->cliente_id,
            'credito_id' => $credito->id,
            'lineas' => [
                ['concepto' => 'Interés cuota 1', 'descripcion' => 'Interés', 'valor' => '30', 'tipo_concepto' => 'interes_corriente', 'cuota_id' => $cuota->id],
            ],
        ])->assertStatus(201)->json('documento');
        $this->postJson('/api/v1/facturacion/documentos/'.$borrador['id'].'/emitir')->assertOk();

        $conceptos = $this->getJson(self::BASE.'?credito_id='.$credito->id)->assertOk()->json('conceptos');

        // El interés de la cuota 1 ya está facturado: no hay nada más que sugerir (no se salta a otro concepto de la misma cuota).
        $this->assertSame([], $conceptos);
    }
}
