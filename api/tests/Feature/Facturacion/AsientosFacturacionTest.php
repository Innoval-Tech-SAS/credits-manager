<?php

namespace Tests\Feature\Facturacion;

use App\Contabilidad\Enums\EventoContable;
use App\Contabilidad\Models\Comprobante;
use App\Contabilidad\Models\ReglaContabilizacion;
use App\Facturacion\Models\DocumentoFacturacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesAuditoriaFixtures;
use Tests\Concerns\CreatesCreditFixtures;
use Tests\Concerns\CreatesFacturacionFixtures;
use Tests\TestCase;

class AsientosFacturacionTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesAuditoriaFixtures;
    use CreatesCreditFixtures;
    use CreatesFacturacionFixtures;
    use RefreshDatabase;

    private const BASE = '/api/v1/facturacion';

    private function borrador(string $valor = '1000'): array
    {
        $credito = $this->crearCredito($this->crearCliente());

        return $this->postJson(self::BASE.'/documentos', [
            'cliente_id' => $credito->cliente_id,
            'credito_id' => $credito->id,
            'lineas' => [['concepto' => 'INTERES.AMORT.ADM', 'descripcion' => 'Interés administrativo', 'valor' => $valor, 'tipo_concepto' => 'otros_gastos']],
        ])->assertStatus(201)->json('documento');
    }

    private function asientoDe(int $documentoId): Comprobante
    {
        $documento = DocumentoFacturacion::findOrFail($documentoId);

        return Comprobante::findOrFail($documento->comprobante_id);
    }

    public function test_emitir_sin_regla_contable_se_rechaza_y_no_consume_numero(): void
    {
        $this->actingAsAdmin();
        $borrador = $this->borrador();

        $this->postJson(self::BASE.'/documentos/'.$borrador['id'].'/emitir')
            ->assertStatus(422)
            ->assertJsonValidationErrors('contabilidad');

        $this->assertDatabaseHas('documentos_facturacion', ['id' => $borrador['id'], 'estado' => 'borrador', 'consecutivo' => null]);
        $this->assertDatabaseCount('comprobantes', 0);

        $this->crearReglasFacturacion();

        $emitida = $this->postJson(self::BASE.'/documentos/'.$borrador['id'].'/emitir')->assertOk()->json('documento');

        $this->assertSame(1, $emitida['consecutivo'], 'El fallo previo no debe dejar huecos en la numeración.');
    }

    public function test_la_factura_genera_un_asiento_cuadrado_con_la_regla_de_emision(): void
    {
        $this->actingAsAdmin();
        $this->crearReglasFacturacion();
        $factura = $this->postJson(self::BASE.'/documentos/'.$this->borrador('1000')['id'].'/emitir')->assertOk()->json('documento');

        $asiento = $this->asientoDe($factura['id']);
        $regla = ReglaContabilizacion::query()->where('evento', EventoContable::FacturaEmision)->firstOrFail();

        $this->assertSame('facturacion', $asiento->modulo_origen);
        $this->assertSame($factura['id'], $asiento->origen_id);
        $this->assertSame(100000, $asiento->total_debito->centavos());
        $this->assertTrue($asiento->total_debito->igualA($asiento->total_credito));
        $this->assertSame($regla->cuenta_debito_id, $asiento->lineas->firstWhere(fn ($l) => $l->debito->esPositivo())->cuenta_id);
        $this->assertSame($regla->cuenta_credito_id, $asiento->lineas->firstWhere(fn ($l) => $l->credito->esPositivo())->cuenta_id);
    }

    public function test_nota_credito_y_nota_debito_generan_asientos_cuadrados_con_su_regla(): void
    {
        $this->actingAsAdmin();
        $this->crearReglasFacturacion();
        $factura = $this->postJson(self::BASE.'/documentos/'.$this->borrador('1000')['id'].'/emitir')->assertOk()->json('documento');
        $linea = $factura['lineas'][0]['id'];

        $credito = $this->postJson(self::BASE.'/documentos/'.$factura['id'].'/notas', [
            'tipo' => 'nota_credito', 'motivo_codigo' => 'ajuste_precio', 'lineas' => [['linea_origen_id' => $linea, 'valor' => '200']],
        ])->assertStatus(201)->json('documento');

        $debito = $this->postJson(self::BASE.'/documentos/'.$factura['id'].'/notas', [
            'tipo' => 'nota_debito', 'motivo_codigo' => 'gastos_por_cobrar', 'lineas' => [['concepto' => 'MORA', 'descripcion' => 'Mora', 'valor' => '50']],
        ])->assertStatus(201)->json('documento');

        $asientoCredito = $this->asientoDe($credito['id']);
        $asientoDebito = $this->asientoDe($debito['id']);
        $reglaCredito = ReglaContabilizacion::query()->where('evento', EventoContable::NotaCreditoFactura)->firstOrFail();
        $reglaDebito = ReglaContabilizacion::query()->where('evento', EventoContable::NotaDebitoFactura)->firstOrFail();

        $this->assertSame(20000, $asientoCredito->total_debito->centavos());
        $this->assertTrue($asientoCredito->total_debito->igualA($asientoCredito->total_credito));
        $this->assertSame($reglaCredito->cuenta_debito_id, $asientoCredito->lineas->firstWhere(fn ($l) => $l->debito->esPositivo())->cuenta_id);

        $this->assertSame(5000, $asientoDebito->total_debito->centavos());
        $this->assertTrue($asientoDebito->total_debito->igualA($asientoDebito->total_credito));
        $this->assertSame($reglaDebito->cuenta_debito_id, $asientoDebito->lineas->firstWhere(fn ($l) => $l->debito->esPositivo())->cuenta_id);
    }
}
