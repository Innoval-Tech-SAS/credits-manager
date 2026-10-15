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

    public function test_sugiere_el_interes_de_las_cuotas_pagadas_sin_facturar(): void
    {
        $this->actingAsAdmin();
        $credito = $this->crearCredito($this->crearCliente());
        // Pendiente: no se sugiere, todavía no se pagó.
        $this->crearCuota($credito, [
            'numero_cuota' => 1,
            'valor_capital' => 250, 'valor_interes' => 30, 'valor_otros_gastos' => 0,
            'estado' => EstadoCuota::Pendiente,
        ]);
        $cuotaPagada = $this->crearCuota($credito, [
            'numero_cuota' => 2,
            'valor_capital' => 260, 'valor_interes' => 20, 'valor_otros_gastos' => 0,
            'valor_pagado' => 280,
            'estado' => EstadoCuota::Pagada,
        ]);
        // Pagada pero sin interés: no hay nada que sugerir de ella.
        $this->crearCuota($credito, [
            'numero_cuota' => 3,
            'valor_capital' => 270, 'valor_interes' => 0, 'valor_otros_gastos' => 0,
            'valor_pagado' => 270,
            'estado' => EstadoCuota::Pagada,
        ]);

        $conceptos = $this->getJson(self::BASE.'?credito_id='.$credito->id)->assertOk()->json('conceptos');

        $this->assertCount(1, $conceptos);
        $this->assertSame('interes_corriente', $conceptos[0]['tipo_concepto']);
        $this->assertSame($cuotaPagada->id, $conceptos[0]['cuota_id']);
        $this->assertSame('20.00', $conceptos[0]['valor']);
    }

    public function test_sugiere_varias_cuotas_pagadas_a_la_vez(): void
    {
        $this->actingAsAdmin();
        $credito = $this->crearCredito($this->crearCliente());
        $cuota1 = $this->crearCuota($credito, ['numero_cuota' => 1, 'valor_interes' => 30, 'valor_pagado' => 280, 'estado' => EstadoCuota::Pagada]);
        $cuota2 = $this->crearCuota($credito, ['numero_cuota' => 2, 'valor_interes' => 20, 'valor_pagado' => 280, 'estado' => EstadoCuota::Pagada]);

        $conceptos = $this->getJson(self::BASE.'?credito_id='.$credito->id)->assertOk()->json('conceptos');

        $this->assertCount(2, $conceptos);
        $this->assertSame([$cuota1->id, $cuota2->id], array_column($conceptos, 'cuota_id'));
    }

    public function test_sin_cuotas_pagadas_no_sugiere_nada(): void
    {
        $this->actingAsAdmin();
        $credito = $this->crearCredito($this->crearCliente());
        $this->crearCuota($credito, ['numero_cuota' => 1, 'valor_interes' => 30, 'estado' => EstadoCuota::Pendiente]);

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

    public function test_excluye_el_interes_ya_facturado(): void
    {
        $this->actingAsAdmin();
        $this->crearReglasFacturacion();
        $credito = $this->crearCredito($this->crearCliente());
        $cuota = $this->crearCuota($credito, [
            'numero_cuota' => 1, 'valor_capital' => 250, 'valor_interes' => 30, 'valor_otros_gastos' => 0,
            'valor_pagado' => 280, 'estado' => EstadoCuota::Pagada,
        ]);

        $this->postJson('/api/v1/facturacion/documentos/intereses-pagados', [
            'credito_id' => $credito->id,
            'cuota_ids' => [$cuota->id],
        ])->assertStatus(201);

        $conceptos = $this->getJson(self::BASE.'?credito_id='.$credito->id)->assertOk()->json('conceptos');

        $this->assertSame([], $conceptos);
    }
}
