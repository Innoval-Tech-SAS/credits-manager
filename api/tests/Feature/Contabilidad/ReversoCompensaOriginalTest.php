<?php

namespace Tests\Feature\Contabilidad;

use App\Contabilidad\Services\ComprobanteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesContabilidadFixtures;
use Tests\TestCase;

/** Un comprobante reversado debe quedar en cero en cada cuenta: el original anulado y su reverso se compensan. */
class ReversoCompensaOriginalTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesContabilidadFixtures;
    use RefreshDatabase;

    public function test_el_balance_y_el_auxiliar_quedan_en_cero_tras_reversar(): void
    {
        $this->actingAsAdmin();
        $tipo = $this->crearTipoComprobante();
        $caja = $this->crearCuenta(['codigo' => '110505', 'nombre' => 'Caja general']);
        $ingresos = $this->crearCuenta(['codigo' => '413505', 'nombre' => 'Intereses', 'naturaleza' => 'credito', 'tipo' => 'ingreso']);

        $original = app(ComprobanteService::class)->crearComprobante($tipo->id, '2026-03-10', [
            ['cuenta_id' => $caja->id, 'debito' => 500, 'credito' => 0],
            ['cuenta_id' => $ingresos->id, 'debito' => 0, 'credito' => 500],
        ], 'Recaudo a reversar', 'manual');

        app(ComprobanteService::class)->reversarComprobante($original->fresh(), 'Error de digitación');

        $balance = collect($this->getJson('/api/v1/contabilidad/balance-prueba?hasta=2026-12-31')->assertOk()->json('saldos'));
        $this->assertSame('0.00', $balance->firstWhere('codigo', '110505')['saldo']);
        $this->assertSame('0.00', $balance->firstWhere('codigo', '413505')['saldo']);

        $this->getJson('/api/v1/contabilidad/auxiliares?cuenta_id='.$caja->id.'&desde=2026-01-01&hasta=2026-12-31')
            ->assertOk()
            ->assertJsonPath('grupos.0.saldo_final', '0.00')
            ->assertJsonPath('grupos.0.total_debito', '500.00')
            ->assertJsonPath('grupos.0.total_credito', '500.00');
    }
}
