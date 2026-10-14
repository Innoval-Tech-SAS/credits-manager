<?php

namespace Tests\Feature\Facturacion;

use App\Contabilidad\Enums\EventoContable;
use App\Contabilidad\Models\Comprobante;
use App\Contabilidad\Models\ReglaContabilizacion;
use App\Empresas\Models\Empresa;
use App\Facturacion\Enums\TipoConceptoFacturacion;
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

    /** Aprueba una nota crédito pendiente con una persona distinta de quien la solicitó (7.1.2), y deja a esa persona como usuario autenticado. */
    private function aprobarNotaCredito(array $notaPendiente, Empresa $empresa): array
    {
        $this->actingAsRolConPermisos('aprobador_nc', [
            'facturacion.aprobar_nota_credito', 'facturacion.listar_facturas', 'facturacion.listar_notas_credito',
        ], $empresa);

        return $this->postJson(self::BASE.'/documentos/'.$notaPendiente['id'].'/aprobar')->assertOk()->json('documento');
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
        $empresa = $this->crearEmpresaDePrueba();
        $admin = $this->actingAsAdmin($empresa);
        $this->crearReglasFacturacion();
        $factura = $this->postJson(self::BASE.'/documentos/'.$this->borrador('1000')['id'].'/emitir')->assertOk()->json('documento');
        $linea = $factura['lineas'][0]['id'];

        $pendiente = $this->postJson(self::BASE.'/documentos/'.$factura['id'].'/notas', [
            'tipo' => 'nota_credito', 'motivo_codigo' => 'ajuste_precio', 'lineas' => [['linea_origen_id' => $linea, 'valor' => '200']],
        ])->assertStatus(201)->json('documento');
        $credito = $this->aprobarNotaCredito($pendiente, $empresa);

        // El aprobador de la nota crédito queda como usuario actual: se retoma al mismo admin para la nota débito.
        $this->actingAs($admin, 'sanctum');
        $debito = $this->postJson(self::BASE.'/documentos/'.$factura['id'].'/notas', [
            'tipo' => 'nota_debito', 'motivo_codigo' => 'gastos_por_cobrar', 'fecha_vencimiento' => '2026-12-01',
            'lineas' => [['concepto' => 'MORA', 'descripcion' => 'Mora', 'valor' => '50']],
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

    public function test_la_nota_credito_reversa_el_iva_proporcionalmente(): void
    {
        $empresa = $this->crearEmpresaDePrueba(['responsable_iva' => true]);
        $this->actingAsAdmin($empresa);
        $this->crearReglasFacturacion();
        $cuentaIva = $this->crearCuenta();
        $this->crearParametrizacionConcepto(TipoConceptoFacturacion::OtrosGastos, ['tarifa_iva' => 19, 'cuenta_iva_id' => $cuentaIva->id]);

        $factura = $this->postJson(self::BASE.'/documentos/'.$this->borrador('1000')['id'].'/emitir')->assertOk()->json('documento');
        $linea = $factura['lineas'][0]['id'];

        // IVA de la factura: 1000 * 19% = 190. Crédito parcial de 500 (la mitad del bruto) debe reversar la mitad del IVA: 95.
        $pendiente = $this->postJson(self::BASE.'/documentos/'.$factura['id'].'/notas', [
            'tipo' => 'nota_credito', 'motivo_codigo' => 'ajuste_precio', 'lineas' => [['linea_origen_id' => $linea, 'valor' => '500']],
        ])->assertStatus(201)->json('documento');
        $credito = $this->aprobarNotaCredito($pendiente, $empresa);

        $asientoCredito = $this->asientoDe($credito['id']);
        $lineaIva = $asientoCredito->lineas->firstWhere(fn ($l) => $l->cuenta_id === $cuentaIva->id);

        $this->assertNotNull($lineaIva, 'La nota crédito debe generar una línea de reversa del IVA.');
        $this->assertSame(9500, $lineaIva->debito->centavos());
        $this->assertTrue($asientoCredito->total_debito->igualA($asientoCredito->total_credito));
    }
}
