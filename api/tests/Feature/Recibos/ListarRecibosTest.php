<?php

namespace Tests\Feature\Recibos;

use App\Core\Dinero\Dinero;
use App\Creditos\Models\Cliente;
use App\Empresas\Models\Empresa;
use App\Pagos\Enums\FormaPago;
use App\Pagos\Models\Pago;
use App\Pagos\Services\PagoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesCreditFixtures;
use Tests\TestCase;

class ListarRecibosTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesCreditFixtures;
    use RefreshDatabase;

    /** @return array{0: Cliente, 1: Pago} */
    private function pagoDeCliente(string $nombre, float $valor): array
    {
        $cliente = $this->crearCliente(['nombre' => $nombre]);
        $credito = $this->crearCredito($cliente, ['valor_prestamo' => 1000, 'valor_cuota' => 1000, 'valor_total' => 1000, 'plazo' => 1]);
        $this->crearCuota($credito, ['numero_cuota' => 1, 'fecha_vencimiento' => now()->addMonth()->toDateString(), 'valor' => 1000, 'valor_interes' => 0, 'valor_capital' => 1000]);

        $pago = app(PagoService::class)->registrarPago($credito->fresh(), Dinero::desdePesos($valor), FormaPago::Efectivo, null, null)['pago'];

        return [$cliente, $pago];
    }

    public function test_sin_autenticar_devuelve_401(): void
    {
        $this->getJson('/api/v1/recibos')->assertStatus(401);
    }

    public function test_asesor_sin_permiso_de_recibos_recibe_403(): void
    {
        $admin = $this->actingAsAdmin();
        $this->actingAsAsesor(Empresa::findOrFail($admin->empresa_id));

        $this->getJson('/api/v1/recibos')->assertStatus(403);
    }

    public function test_rechaza_filtros_invalidos(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/v1/recibos?estado=inventado')->assertStatus(422)->assertJsonValidationErrors(['estado']);
        $this->getJson('/api/v1/recibos?desde=2026-10-10&hasta=2026-10-01')->assertStatus(422)->assertJsonValidationErrors(['hasta']);
        $this->getJson('/api/v1/recibos?tercero_id=999999')->assertStatus(422)->assertJsonValidationErrors(['tercero_id']);
    }

    public function test_filtra_por_tercero_estado_y_fecha(): void
    {
        $this->actingAsAdmin();
        [$ana, $pagoAna] = $this->pagoDeCliente('Ana Ruiz', 100);
        [$beto] = $this->pagoDeCliente('Beto Soto', 200);

        $this->postJson("/api/v1/pagos/{$pagoAna->id}/reversar", ['motivo' => 'Pago registrado por error'])->assertOk();

        $this->getJson('/api/v1/recibos')->assertOk()->assertJsonPath('total', 2);

        $anulados = $this->getJson('/api/v1/recibos?estado=anulado');
        $anulados->assertJsonPath('total', 1);
        $anulados->assertJsonPath('data.0.pago.cliente.tercero.nombre', 'Ana Ruiz');
        $anulados->assertJsonPath('data.0.estado', 'anulado');

        $this->getJson('/api/v1/recibos?estado=emitido')->assertJsonPath('data.0.pago.cliente.tercero.nombre', 'Beto Soto');

        $this->getJson("/api/v1/recibos?tercero_id={$beto->tercero_id}")->assertJsonPath('total', 1);

        $hoy = now('America/Bogota')->toDateString();
        $this->getJson("/api/v1/recibos?desde={$hoy}&hasta={$hoy}")->assertJsonPath('total', 2);
        $this->getJson('/api/v1/recibos?desde=2000-01-01&hasta=2000-01-02')->assertJsonPath('total', 0);
    }
}
