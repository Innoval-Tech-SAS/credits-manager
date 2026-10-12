<?php

namespace Tests\Feature\Facturacion;

use App\Empresas\Models\Empresa;
use App\Facturacion\Enums\TipoConceptoFacturacion;
use App\Terceros\Enums\TipoPersona;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesCreditFixtures;
use Tests\Concerns\CreatesFacturacionFixtures;
use Tests\Concerns\CreatesGastosFixtures;
use Tests\TestCase;

/**
 * IVA/retenciones de `MotorFiscalFacturacionService`, a través del flujo real de crear un borrador: quien
 * recibe el pago es la empresa (DDA), así que el IVA y la autorretención dependen de sus atributos fiscales,
 * no de los del cliente — al revés que en Gastos.
 */
class MotorFiscalFacturacionTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesCreditFixtures;
    use CreatesFacturacionFixtures;
    use CreatesGastosFixtures;
    use RefreshDatabase;

    private const BASE = '/api/v1/facturacion/documentos';

    private function lineaOtrosGastos(string $valor): array
    {
        return [['concepto' => 'Otros', 'descripcion' => 'Otros gastos', 'valor' => $valor, 'tipo_concepto' => 'otros_gastos']];
    }

    private function crearBorrador(int $clienteId, int $creditoId, string $valor): array
    {
        return $this->postJson(self::BASE, [
            'cliente_id' => $clienteId, 'credito_id' => $creditoId, 'lineas' => $this->lineaOtrosGastos($valor),
        ])->assertStatus(201)->json('documento');
    }

    public function test_no_aplica_iva_si_la_empresa_no_es_responsable_de_iva(): void
    {
        $admin = $this->actingAsAdmin();
        $this->crearReglasFacturacion();
        Empresa::find($admin->empresa_id)->update(['responsable_iva' => false]);
        $this->crearParametrizacionConcepto(TipoConceptoFacturacion::OtrosGastos, ['tarifa_iva' => 19]);
        $credito = $this->crearCredito($this->crearCliente());

        $documento = $this->crearBorrador($credito->cliente_id, $credito->id, '1000000');

        $this->assertSame('0.00', $documento['lineas'][0]['valor_iva']);
    }

    public function test_aplica_iva_si_la_empresa_es_responsable_y_el_concepto_tiene_tarifa(): void
    {
        $admin = $this->actingAsAdmin();
        $this->crearReglasFacturacion();
        Empresa::find($admin->empresa_id)->update(['responsable_iva' => true]);
        $this->crearParametrizacionConcepto(TipoConceptoFacturacion::OtrosGastos, ['tarifa_iva' => 19]);
        $credito = $this->crearCredito($this->crearCliente());

        $documento = $this->crearBorrador($credito->cliente_id, $credito->id, '1000000');

        $this->assertSame('190000.00', $documento['lineas'][0]['valor_iva']);
    }

    public function test_la_retencion_nunca_aplica_si_el_cliente_es_persona_natural(): void
    {
        $admin = $this->actingAsAdmin();
        $this->crearReglasFacturacion();
        Empresa::find($admin->empresa_id)->update(['autorretenedor' => false]);
        $this->crearParametrizacionConcepto(TipoConceptoFacturacion::OtrosGastos, [
            'aplica_retefuente' => true, 'tarifa_retefuente' => 10, 'cuenta_retefuente_id' => $this->crearCuenta()->id,
        ]);
        $credito = $this->crearCredito($this->crearCliente());

        $documento = $this->crearBorrador($credito->cliente_id, $credito->id, '1000000');

        $this->assertSame('0.00', $documento['lineas'][0]['valor_retefuente']);
    }

    public function test_la_retencion_nunca_aplica_si_la_empresa_es_autorretenedora(): void
    {
        $admin = $this->actingAsAdmin();
        $this->crearReglasFacturacion();
        Empresa::find($admin->empresa_id)->update(['autorretenedor' => true]);
        $this->crearParametrizacionConcepto(TipoConceptoFacturacion::OtrosGastos, [
            'aplica_retefuente' => true, 'tarifa_retefuente' => 10, 'cuenta_retefuente_id' => $this->crearCuenta()->id,
        ]);
        $credito = $this->crearCredito($this->crearCliente(['tipo_persona' => TipoPersona::Juridica]));

        $documento = $this->crearBorrador($credito->cliente_id, $credito->id, '1000000');

        $this->assertSame('0.00', $documento['lineas'][0]['valor_retefuente']);
    }

    public function test_retefuente_reteica_y_reteiva_aplican_con_cliente_juridico_y_empresa_no_autorretenedora(): void
    {
        $admin = $this->actingAsAdmin();
        $this->crearReglasFacturacion();
        $municipio = $this->crearMunicipio();
        $actividad = $this->crearActividadEconomica();
        $this->crearTarifaReteIca($municipio, $actividad, 6.9);
        Empresa::find($admin->empresa_id)->update([
            'responsable_iva' => true, 'autorretenedor' => false,
            'municipio_id' => $municipio->id, 'actividad_economica_id' => $actividad->id,
        ]);
        $this->crearParametrizacionConcepto(TipoConceptoFacturacion::OtrosGastos, [
            'tarifa_iva' => 19, 'cuenta_iva_id' => $this->crearCuenta()->id,
            'aplica_retefuente' => true, 'tarifa_retefuente' => 10, 'cuenta_retefuente_id' => $this->crearCuenta()->id,
            'aplica_reteica' => true, 'cuenta_reteica_id' => $this->crearCuenta()->id,
            'aplica_reteiva' => true, 'tarifa_reteiva' => 15, 'cuenta_reteiva_id' => $this->crearCuenta()->id,
        ]);
        $credito = $this->crearCredito($this->crearCliente(['tipo_persona' => TipoPersona::Juridica]));

        $linea = $this->crearBorrador($credito->cliente_id, $credito->id, '1000000')['lineas'][0];

        $this->assertSame('190000.00', $linea['valor_iva']);
        $this->assertSame('100000.00', $linea['valor_retefuente']);
        $this->assertSame('6900.00', $linea['valor_reteica']);
        $this->assertSame('28500.00', $linea['valor_reteiva']);
    }

    public function test_retefuente_respeta_la_base_minima_en_uvt(): void
    {
        $admin = $this->actingAsAdmin();
        $this->crearReglasFacturacion();
        $this->crearValorUvt(2026, 47065);
        Empresa::find($admin->empresa_id)->update(['autorretenedor' => false]);
        $this->crearParametrizacionConcepto(TipoConceptoFacturacion::OtrosGastos, [
            'aplica_retefuente' => true, 'base_minima_uvt' => 30, 'tarifa_retefuente' => 10, 'cuenta_retefuente_id' => $this->crearCuenta()->id,
        ]);
        $credito = $this->crearCredito($this->crearCliente(['tipo_persona' => TipoPersona::Juridica]));

        // Base mínima: 30 UVT * 47065 = 1.411.950. Por debajo, no retiene; por encima, sí.
        $pordebajo = $this->crearBorrador($credito->cliente_id, $credito->id, '1000000');
        $this->assertSame('0.00', $pordebajo['lineas'][0]['valor_retefuente']);

        $porencima = $this->crearBorrador($credito->cliente_id, $credito->id, '2000000');
        $this->assertSame('200000.00', $porencima['lineas'][0]['valor_retefuente']);
    }
}
