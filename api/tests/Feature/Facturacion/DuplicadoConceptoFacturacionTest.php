<?php

namespace Tests\Feature\Facturacion;

use App\Creditos\Enums\EstadoCuota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesCreditFixtures;
use Tests\Concerns\CreatesFacturacionFixtures;
use Tests\TestCase;

/** Que una misma cuota+concepto no se facture dos veces, salvo que la factura que la contenía haya quedado anulada. */
class DuplicadoConceptoFacturacionTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesCreditFixtures;
    use CreatesFacturacionFixtures;
    use RefreshDatabase;

    private const BASE = '/api/v1/facturacion/documentos';

    private function lineaCapital(int $cuotaId, string $valor = '250'): array
    {
        return ['concepto' => 'Capital cuota 1', 'descripcion' => 'Capital', 'valor' => $valor, 'tipo_concepto' => 'capital', 'cuota_id' => $cuotaId];
    }

    public function test_no_se_puede_facturar_dos_veces_la_misma_cuota_y_concepto(): void
    {
        $this->actingAsAdmin();
        $this->crearReglasFacturacion();
        $credito = $this->crearCredito($this->crearCliente());
        $cuota = $this->crearCuota($credito, [
            'numero_cuota' => 1, 'valor_capital' => 250, 'valor_interes' => 0, 'valor_otros_gastos' => 0,
            'estado' => EstadoCuota::Pendiente,
        ]);

        $primero = $this->postJson(self::BASE, [
            'cliente_id' => $credito->cliente_id, 'credito_id' => $credito->id,
            'lineas' => [$this->lineaCapital($cuota->id)],
        ])->assertStatus(201)->json('documento');
        $this->postJson(self::BASE.'/'.$primero['id'].'/emitir')->assertOk();

        $this->postJson(self::BASE, [
            'cliente_id' => $credito->cliente_id, 'credito_id' => $credito->id,
            'lineas' => [$this->lineaCapital($cuota->id)],
        ])->assertStatus(422)->assertJsonValidationErrors('lineas');
    }

    public function test_un_borrador_sin_emitir_no_bloquea_el_mismo_combo(): void
    {
        $this->actingAsAdmin();
        $this->crearReglasFacturacion();
        $credito = $this->crearCredito($this->crearCliente());
        $cuota = $this->crearCuota($credito, [
            'numero_cuota' => 1, 'valor_capital' => 250, 'valor_interes' => 0, 'valor_otros_gastos' => 0,
            'estado' => EstadoCuota::Pendiente,
        ]);

        $this->postJson(self::BASE, [
            'cliente_id' => $credito->cliente_id, 'credito_id' => $credito->id,
            'lineas' => [$this->lineaCapital($cuota->id)],
        ])->assertStatus(201);

        // El primero sigue en borrador (nunca se emitió): el combo no está realmente facturado todavía.
        $this->postJson(self::BASE, [
            'cliente_id' => $credito->cliente_id, 'credito_id' => $credito->id,
            'lineas' => [$this->lineaCapital($cuota->id)],
        ])->assertStatus(201);
    }

    public function test_anular_la_factura_con_una_nota_credito_total_libera_el_combo_para_una_nueva_factura(): void
    {
        $this->actingAsAdmin();
        $this->crearReglasFacturacion();
        $credito = $this->crearCredito($this->crearCliente());
        $cuota = $this->crearCuota($credito, [
            'numero_cuota' => 1, 'valor_capital' => 250, 'valor_interes' => 0, 'valor_otros_gastos' => 0,
            'estado' => EstadoCuota::Pendiente,
        ]);

        $borrador = $this->postJson(self::BASE, [
            'cliente_id' => $credito->cliente_id, 'credito_id' => $credito->id,
            'lineas' => [$this->lineaCapital($cuota->id)],
        ])->assertStatus(201)->json('documento');
        $factura = $this->postJson(self::BASE.'/'.$borrador['id'].'/emitir')->assertOk()->json('documento');

        $this->postJson(self::BASE.'/'.$factura['id'].'/anular', ['motivo' => 'Factura emitida por error'])->assertOk();

        // La cuota/concepto vuelve a estar disponible: la factura que la contenía quedó anulada en su totalidad.
        $this->postJson(self::BASE, [
            'cliente_id' => $credito->cliente_id, 'credito_id' => $credito->id,
            'lineas' => [$this->lineaCapital($cuota->id)],
        ])->assertStatus(201);
    }

    public function test_una_nota_credito_parcial_no_libera_el_combo(): void
    {
        $this->actingAsAdmin();
        $this->crearReglasFacturacion();
        $credito = $this->crearCredito($this->crearCliente());
        $cuota = $this->crearCuota($credito, [
            'numero_cuota' => 1, 'valor_capital' => 250, 'valor_interes' => 0, 'valor_otros_gastos' => 0,
            'estado' => EstadoCuota::Pendiente,
        ]);

        $borrador = $this->postJson(self::BASE, [
            'cliente_id' => $credito->cliente_id, 'credito_id' => $credito->id,
            'lineas' => [$this->lineaCapital($cuota->id)],
        ])->assertStatus(201)->json('documento');
        $factura = $this->postJson(self::BASE.'/'.$borrador['id'].'/emitir')->assertOk()->json('documento');
        $lineaId = $factura['lineas'][0]['id'];

        $this->postJson(self::BASE.'/'.$factura['id'].'/notas', [
            'tipo' => 'nota_credito', 'motivo_codigo' => 'ajuste_precio',
            'lineas' => [['linea_origen_id' => $lineaId, 'valor' => '100']],
        ])->assertStatus(201);

        $this->postJson(self::BASE, [
            'cliente_id' => $credito->cliente_id, 'credito_id' => $credito->id,
            'lineas' => [$this->lineaCapital($cuota->id)],
        ])->assertStatus(422)->assertJsonValidationErrors('lineas');
    }
}
