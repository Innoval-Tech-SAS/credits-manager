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

    public function test_sugiere_capital_interes_y_otros_gastos_de_las_cuotas_pendientes_y_vencidas(): void
    {
        $this->actingAsAdmin();
        $credito = $this->crearCredito($this->crearCliente());
        $cuotaVencida = $this->crearCuota($credito, [
            'numero_cuota' => 1,
            'fecha_vencimiento' => '2026-02-01',
            'valor_capital' => 250, 'valor_interes' => 30, 'valor_otros_gastos' => 5,
            'estado' => EstadoCuota::Vencida,
        ]);
        $this->crearCuota($credito, [
            'numero_cuota' => 2,
            'fecha_vencimiento' => '2026-03-01',
            'valor_capital' => 260, 'valor_interes' => 20, 'valor_otros_gastos' => 0,
            'estado' => EstadoCuota::Pendiente,
        ]);
        // Pagada: no debe sugerir nada de esta cuota, aunque sus valores no sean cero.
        $this->crearCuota($credito, [
            'numero_cuota' => 3,
            'fecha_vencimiento' => '2026-04-01',
            'valor_capital' => 270, 'valor_interes' => 10, 'valor_otros_gastos' => 0,
            'estado' => EstadoCuota::Pagada,
        ]);

        $conceptos = $this->getJson(self::BASE.'?credito_id='.$credito->id)->assertOk()->json('conceptos');

        $this->assertCount(5, $conceptos);
        $primeros3 = array_slice($conceptos, 0, 3);
        $this->assertSame(['capital', 'interes_corriente', 'otros_gastos'], array_column($primeros3, 'tipo_concepto'));
        $this->assertSame([$cuotaVencida->id, $cuotaVencida->id, $cuotaVencida->id], array_column($primeros3, 'cuota_id'));
        $this->assertSame(['250.00', '30.00', '5.00'], array_column($primeros3, 'valor'));
        $this->assertSame('2026-02-01', $primeros3[0]['fecha_vencimiento']);

        $siguientes = array_slice($conceptos, 3);
        // La cuota 2 tiene otros_gastos en cero: solo capital e interés, sin la mora (no se precarga nunca).
        $this->assertSame(['capital', 'interes_corriente'], array_column($siguientes, 'tipo_concepto'));
    }

    public function test_un_credito_de_otra_empresa_responde_422(): void
    {
        $this->actingAsAdmin();
        $creditoAjeno = $this->crearCredito($this->crearCliente());

        $this->actingAsAdmin();

        $this->getJson(self::BASE.'?credito_id='.$creditoAjeno->id)->assertStatus(422)->assertJsonValidationErrors('credito_id');
    }

    public function test_excluye_lo_ya_facturado_en_una_factura_emitida(): void
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
                ['concepto' => 'Capital cuota 1', 'descripcion' => 'Capital', 'valor' => '250', 'tipo_concepto' => 'capital', 'cuota_id' => $cuota->id],
            ],
        ])->assertStatus(201)->json('documento');
        $this->postJson('/api/v1/facturacion/documentos/'.$borrador['id'].'/emitir')->assertOk();

        $conceptos = $this->getJson(self::BASE.'?credito_id='.$credito->id)->assertOk()->json('conceptos');

        // El capital ya facturado no se vuelve a sugerir; el interés, no facturado, sigue apareciendo.
        $this->assertSame(['interes_corriente'], array_column($conceptos, 'tipo_concepto'));
    }
}
