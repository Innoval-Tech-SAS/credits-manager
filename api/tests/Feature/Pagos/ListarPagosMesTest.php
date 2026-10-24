<?php

namespace Tests\Feature\Pagos;

use App\Core\Dinero\Dinero;
use App\Creditos\Models\Credito;
use App\Pagos\Enums\FormaPago;
use App\Pagos\Models\Pago;
use App\Pagos\Services\PagoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesContabilidadFixtures;
use Tests\Concerns\CreatesCreditFixtures;
use Tests\TestCase;

class ListarPagosMesTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesContabilidadFixtures;
    use CreatesCreditFixtures;
    use RefreshDatabase;

    private function creditoConCuota(): Credito
    {
        $credito = $this->crearCredito($this->crearCliente(), ['valor_prestamo' => 300, 'valor_cuota' => 100, 'valor_total' => 300, 'plazo' => 3]);
        $this->crearCuota($credito, ['numero_cuota' => 1, 'fecha_vencimiento' => now()->addMonth()->toDateString(), 'valor' => 300, 'valor_capital' => 300]);

        return $credito;
    }

    private function pagar(Credito $credito): Pago
    {
        $this->crearReglasDeCreditoYPago();

        return app(PagoService::class)->registrarPago($credito->fresh(), Dinero::desdePesos(100), FormaPago::Efectivo, null, null)['pago'];
    }

    public function test_filtra_el_historial_por_mes_de_pago(): void
    {
        $this->actingAsAdmin();
        $credito = $this->creditoConCuota();

        $deMarzo = $this->pagar($credito);
        $deHoy = $this->pagar($credito);
        Pago::query()->whereKey($deMarzo->id)->update(['fecha_pago' => '2026-03-10 10:00:00']);

        $this->getJson('/api/v1/pagos?mes=2026-03')->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.id', $deMarzo->id);
        $this->getJson('/api/v1/pagos?mes=2026-02')->assertJsonPath('total', 0);
        $this->getJson('/api/v1/pagos?mes='.now('America/Bogota')->format('Y-m'))->assertJsonPath('total', 1)->assertJsonPath('data.0.id', $deHoy->id);
    }

    public function test_rechaza_un_mes_con_formato_invalido(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/v1/pagos?mes=2026-13')->assertStatus(422)->assertJsonValidationErrors(['mes']);
        $this->getJson('/api/v1/pagos?mes=03-2026')->assertStatus(422)->assertJsonValidationErrors(['mes']);
        $this->postJson('/api/v1/pagos/exportar', ['formato' => 'xlsx', 'mes' => 'marzo'])->assertStatus(422)->assertJsonValidationErrors(['mes']);
    }

    public function test_el_listado_muestra_la_reversa_del_pago(): void
    {
        $this->actingAsAdmin();
        $pago = $this->pagar($this->creditoConCuota());

        $this->postJson("/api/v1/pagos/{$pago->id}/reversar", ['motivo' => 'Pago registrado por error'])->assertOk();

        $this->getJson('/api/v1/pagos')->assertJsonPath('data.0.reversa.motivo', 'Pago registrado por error');
    }

    public function test_recorrer_todas_las_paginas_no_repite_ni_omite_pagos_con_la_misma_fecha(): void
    {
        $this->actingAsAdmin();
        $ids = collect(range(1, 5))->map(fn () => $this->pagar($this->creditoConCuota())->id);
        Pago::query()->whereKey($ids)->update(['fecha_pago' => '2026-05-10 10:00:00']);

        $vistos = collect();
        for ($pagina = 1; $pagina <= 3; $pagina++) {
            $vistos->push(...$this->getJson("/api/v1/pagos?per_page=2&page={$pagina}")->json('data.*.id'));
        }

        $this->assertSame($ids->sort()->values()->all(), $vistos->unique()->sort()->values()->all());
        $this->assertCount($ids->count(), $vistos);
    }
}
