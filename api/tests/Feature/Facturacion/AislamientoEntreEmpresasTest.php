<?php

namespace Tests\Feature\Facturacion;

use App\Creditos\Enums\EstadoCuota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesCreditFixtures;
use Tests\Concerns\CreatesFacturacionFixtures;
use Tests\TestCase;

class AislamientoEntreEmpresasTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesCreditFixtures;
    use CreatesFacturacionFixtures;
    use RefreshDatabase;

    private const BASE = '/api/v1/facturacion/documentos';

    public function test_los_conceptos_sugeridos_de_un_credito_de_otra_empresa_no_se_filtran_por_404(): void
    {
        $this->actingAsAdmin();
        $creditoAjeno = $this->crearCredito($this->crearCliente());
        $this->crearCuota($creditoAjeno, ['numero_cuota' => 1, 'estado' => EstadoCuota::Pendiente]);

        $this->actingAsAdmin();

        // Un id de otra empresa no existe para el scope del usuario actual: la regla de validación lo rechaza en 422.
        $this->getJson(self::BASE.'/conceptos-sugeridos?credito_id='.$creditoAjeno->id)
            ->assertStatus(422)
            ->assertJsonValidationErrors('credito_id');
    }

    public function test_no_se_puede_crear_un_borrador_sobre_un_credito_de_otra_empresa(): void
    {
        $this->actingAsAdmin();
        $creditoAjeno = $this->crearCredito($this->crearCliente());

        $this->actingAsAdmin();
        $miCliente = $this->crearCliente();

        $this->postJson(self::BASE, [
            'cliente_id' => $miCliente->id,
            'credito_id' => $creditoAjeno->id,
            'lineas' => [['concepto' => 'Otros', 'descripcion' => 'Otros gastos', 'valor' => '1000', 'tipo_concepto' => 'otros_gastos']],
        ])->assertStatus(422)->assertJsonValidationErrors('credito_id');
    }

    public function test_no_se_puede_crear_un_borrador_con_una_cuota_de_otro_credito(): void
    {
        $this->actingAsAdmin();
        $credito = $this->crearCredito($this->crearCliente());
        $otroCredito = $this->crearCredito($this->crearCliente());
        $cuotaDeOtroCredito = $this->crearCuota($otroCredito, ['numero_cuota' => 1, 'estado' => EstadoCuota::Pendiente]);

        $this->postJson(self::BASE, [
            'cliente_id' => $credito->cliente_id,
            'credito_id' => $credito->id,
            'lineas' => [[
                'concepto' => 'Capital', 'descripcion' => 'Capital', 'valor' => '300',
                'tipo_concepto' => 'capital', 'cuota_id' => $cuotaDeOtroCredito->id,
            ]],
        ])->assertStatus(422)->assertJsonValidationErrors('lineas.0.cuota_id');
    }

    public function test_no_se_puede_filtrar_el_listado_por_un_tercero_de_otra_empresa(): void
    {
        $this->actingAsAdmin();
        $clienteAjeno = $this->crearCliente();

        $this->actingAsAdmin();

        $this->getJson(self::BASE.'?tercero_id='.$clienteAjeno->tercero_id)
            ->assertStatus(422)
            ->assertJsonValidationErrors('tercero_id');
    }

    public function test_un_admin_no_ve_facturas_de_otra_empresa_en_el_listado(): void
    {
        $this->actingAsAdmin();
        $this->crearReglasFacturacion();
        $credito = $this->crearCredito($this->crearCliente());
        $borrador = $this->postJson(self::BASE, [
            'cliente_id' => $credito->cliente_id, 'credito_id' => $credito->id,
            'lineas' => [['concepto' => 'Otros', 'descripcion' => 'Otros gastos', 'valor' => '1000', 'tipo_concepto' => 'otros_gastos']],
        ])->assertStatus(201)->json('documento');
        $this->postJson(self::BASE.'/'.$borrador['id'].'/emitir')->assertOk();

        $this->actingAsAdmin();

        $this->getJson(self::BASE)->assertOk()->assertJsonCount(0, 'data');
    }
}
