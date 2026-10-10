<?php

namespace Tests\Feature\Facturacion;

use App\Creditos\Models\Credito;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\AfirmaAuditoria;
use Tests\Concerns\CreatesAuditoriaFixtures;
use Tests\Concerns\CreatesCreditFixtures;
use Tests\Concerns\CreatesFacturacionFixtures;
use Tests\TestCase;

class DocumentosFacturacionTest extends TestCase
{
    use ActsAsAdmin;
    use AfirmaAuditoria;
    use CreatesAuditoriaFixtures;
    use CreatesCreditFixtures;
    use CreatesFacturacionFixtures;
    use RefreshDatabase;

    private const BASE = '/api/v1/facturacion/documentos';

    private function credito(): Credito
    {
        $this->crearReglasFacturacion();

        return $this->crearCredito($this->crearCliente(['nombre' => 'Ana Gomez']));
    }

    /** @param list<string> $valores valores en pesos, uno por línea */
    private function lineas(array $valores = ['1000']): array
    {
        return array_map(fn (string $valor) => [
            'concepto' => 'INTERES.AMORT.ADM',
            'descripcion' => 'Interés administrativo',
            'valor' => $valor,
        ], $valores);
    }

    private function borrador(Credito $credito, array $valores = ['1000']): array
    {
        return $this->postJson(self::BASE, ['credito_id' => $credito->id, 'lineas' => $this->lineas($valores)])
            ->assertStatus(201)
            ->json('documento');
    }

    private function factura(Credito $credito, array $valores = ['1000']): array
    {
        $borrador = $this->borrador($credito, $valores);

        return $this->postJson(self::BASE.'/'.$borrador['id'].'/emitir')->assertOk()->json('documento');
    }

    public function test_sin_autenticar_recibe_401(): void
    {
        $this->getJson(self::BASE)->assertStatus(401);
        $this->postJson(self::BASE, [])->assertStatus(401);
    }

    public function test_asesor_sin_permiso_recibe_403(): void
    {
        $this->actingAsAsesor();

        $this->getJson(self::BASE)->assertStatus(403);
        $this->postJson(self::BASE, ['credito_id' => 1, 'lineas' => $this->lineas()])->assertStatus(403);
    }

    public function test_crea_un_borrador_desde_un_credito_desembolsado_sin_numero(): void
    {
        $this->actingAsAdmin();
        $credito = $this->credito();

        $borrador = $this->borrador($credito, ['1500.50', '200']);

        $this->assertSame('borrador', $borrador['estado']);
        $this->assertNull($borrador['numero']);
        $this->assertDatabaseHas('documentos_facturacion', [
            'id' => $borrador['id'], 'tipo' => 'factura', 'estado' => 'borrador', 'consecutivo' => null,
            'cliente_id' => $credito->cliente_id, 'credito_id' => $credito->id,
        ]);
        $this->assertDatabaseHas('lineas_documento_facturacion', ['documento_id' => $borrador['id'], 'valor' => 150050]);
        $this->assertCount(2, $borrador['lineas']);
    }

    public function test_rechaza_un_credito_inexistente_y_lineas_invalidas(): void
    {
        $this->actingAsAdmin();

        $this->postJson(self::BASE, ['credito_id' => 9999, 'lineas' => $this->lineas()])->assertStatus(422)->assertJsonValidationErrors('credito_id');
        $this->postJson(self::BASE, ['credito_id' => $this->credito()->id, 'lineas' => []])->assertStatus(422)->assertJsonValidationErrors('lineas');
        $this->postJson(self::BASE, ['credito_id' => $this->credito()->id, 'lineas' => $this->lineas(['0'])])
            ->assertStatus(422)
            ->assertJsonValidationErrors('lineas.0.valor');
    }

    public function test_emite_facturas_con_numeracion_consecutiva_sin_huecos(): void
    {
        $this->actingAsAdmin();
        $credito = $this->credito();

        $primera = $this->factura($credito);
        $segunda = $this->factura($credito);

        $this->assertSame(1, $primera['consecutivo']);
        $this->assertSame('F 1', $primera['numero']);
        $this->assertSame(2, $segunda['consecutivo']);
        $this->assertSame('F 2', $segunda['numero']);
        $this->assertSame('emitido', $primera['estado']);
    }

    public function test_un_documento_emitido_no_se_edita_ni_se_borra_ni_se_vuelve_a_emitir(): void
    {
        $this->actingAsAdmin();
        $factura = $this->factura($this->credito());

        $this->patchJson(self::BASE.'/'.$factura['id'], ['lineas' => $this->lineas()])->assertStatus(422)->assertJsonValidationErrors('documento');
        $this->deleteJson(self::BASE.'/'.$factura['id'])->assertStatus(422)->assertJsonValidationErrors('documento');
        $this->postJson(self::BASE.'/'.$factura['id'].'/emitir')->assertStatus(422)->assertJsonValidationErrors('documento');
    }

    public function test_un_borrador_se_edita_y_se_elimina(): void
    {
        $this->actingAsAdmin();
        $borrador = $this->borrador($this->credito(), ['1000', '500']);

        $this->patchJson(self::BASE.'/'.$borrador['id'], ['lineas' => $this->lineas(['750'])])
            ->assertOk()
            ->assertJsonPath('totales.total', '750.00');

        $this->deleteJson(self::BASE.'/'.$borrador['id'])->assertStatus(204);
        $this->assertSoftDeleted('documentos_facturacion', ['id' => $borrador['id']]);
    }

    public function test_nota_credito_no_acredita_mas_que_lo_que_queda_de_la_linea_y_anula_la_factura(): void
    {
        $this->actingAsAdmin();
        $factura = $this->factura($this->credito(), ['1000']);
        $lineaId = $factura['lineas'][0]['id'];

        $primera = $this->postJson(self::BASE.'/'.$factura['id'].'/notas', [
            'tipo' => 'nota_credito', 'motivo' => 'Corrección parcial',
            'lineas' => [['linea_origen_id' => $lineaId, 'valor' => '600']],
        ])->assertStatus(201);

        $this->assertSame('NC 1', $primera->json('documento.numero'));

        $this->postJson(self::BASE.'/'.$factura['id'].'/notas', [
            'tipo' => 'nota_credito', 'motivo' => 'Exceso',
            'lineas' => [['linea_origen_id' => $lineaId, 'valor' => '500']],
        ])->assertStatus(422)->assertJsonValidationErrors('lineas');

        $this->postJson(self::BASE.'/'.$factura['id'].'/notas', [
            'tipo' => 'nota_credito', 'motivo' => 'Cierre',
            'lineas' => [['linea_origen_id' => $lineaId, 'valor' => '400']],
        ])->assertStatus(201);

        $detalle = $this->getJson(self::BASE.'/'.$factura['id'])->assertOk();

        $this->assertTrue($detalle->json('totales.anulada'));
        $this->assertSame('0.00', $detalle->json('totales.saldo'));
        $this->assertEventoAuditoria('anular', 'facturacion', 'documento_facturacion', $primera->json('documento.id'));
    }

    public function test_nota_credito_exige_una_linea_de_su_factura(): void
    {
        $this->actingAsAdmin();
        $factura = $this->factura($this->credito());
        $otra = $this->factura($this->credito());

        $this->postJson(self::BASE.'/'.$factura['id'].'/notas', [
            'tipo' => 'nota_credito', 'motivo' => 'Línea ajena',
            'lineas' => [['linea_origen_id' => $otra['lineas'][0]['id'], 'valor' => '100']],
        ])->assertStatus(422)->assertJsonValidationErrors('lineas');

        $this->postJson(self::BASE.'/'.$factura['id'].'/notas', [
            'tipo' => 'nota_credito', 'motivo' => 'Sin línea',
            'lineas' => [['valor' => '100']],
        ])->assertStatus(422)->assertJsonValidationErrors('lineas.0.linea_origen_id');
    }

    public function test_nota_debito_sobre_la_factura_con_concepto_libre(): void
    {
        $this->actingAsAdmin();
        $factura = $this->factura($this->credito(), ['1000']);

        $nota = $this->postJson(self::BASE.'/'.$factura['id'].'/notas', [
            'tipo' => 'nota_debito', 'motivo' => 'Cargo adicional',
            'lineas' => [['concepto' => 'MORA', 'descripcion' => 'Gastos de cobranza', 'valor' => '50']],
        ])->assertStatus(201);

        $this->assertSame('ND 1', $nota->json('documento.numero'));

        $detalle = $this->getJson(self::BASE.'/'.$factura['id'])->assertOk();
        $this->assertSame('1050.00', $detalle->json('totales.saldo'));
        $this->assertFalse($detalle->json('totales.anulada'));
    }

    public function test_nota_debito_que_supera_el_tope_configurado_de_la_empresa_se_rechaza(): void
    {
        $this->actingAsAdmin();
        $factura = $this->factura($this->credito(), ['1000']);

        // El default es 1.000.000; una nota de 1.000.000,01 lo supera.
        $this->postJson(self::BASE.'/'.$factura['id'].'/notas', [
            'tipo' => 'nota_debito', 'motivo' => 'Cargo excesivo',
            'lineas' => [['concepto' => 'MORA', 'descripcion' => 'Gastos de cobranza', 'valor' => '1000000.01']],
        ])->assertStatus(422)->assertJsonValidationErrors('lineas');

        $this->assertDatabaseMissing('documentos_facturacion', ['tipo' => 'nota_debito']);
    }

    public function test_nota_debito_dentro_del_tope_reconfigurado_se_acepta(): void
    {
        $this->actingAsAdmin();
        $this->putJson('/api/v1/facturacion/configuracion', ['nota_debito_tope' => '100'])->assertOk();

        $factura = $this->factura($this->credito(), ['1000']);

        $this->postJson(self::BASE.'/'.$factura['id'].'/notas', [
            'tipo' => 'nota_debito', 'motivo' => 'Cargo pequeño',
            'lineas' => [['concepto' => 'MORA', 'descripcion' => 'Gastos de cobranza', 'valor' => '50']],
        ])->assertStatus(201);

        $this->postJson(self::BASE.'/'.$factura['id'].'/notas', [
            'tipo' => 'nota_debito', 'motivo' => 'Cargo que ya no cabe',
            'lineas' => [['concepto' => 'MORA', 'descripcion' => 'Gastos de cobranza', 'valor' => '150']],
        ])->assertStatus(422)->assertJsonValidationErrors('lineas');
    }

    public function test_el_pdf_solo_sale_de_un_documento_emitido(): void
    {
        $this->actingAsAdmin();
        $borrador = $this->borrador($this->credito());
        $factura = $this->factura($this->credito());

        $this->get(self::BASE.'/'.$borrador['id'].'/pdf')->assertStatus(422);

        $respuesta = $this->get(self::BASE.'/'.$factura['id'].'/pdf')->assertOk();
        $this->assertSame('application/pdf', $respuesta->headers->get('Content-Type'));
    }

    public function test_un_documento_de_otra_empresa_responde_404(): void
    {
        $this->actingAsAdmin();
        $factura = $this->factura($this->credito());

        $this->actingAsAdmin();

        $this->getJson(self::BASE.'/'.$factura['id'])->assertStatus(404);
        $this->postJson(self::BASE.'/'.$factura['id'].'/emitir')->assertStatus(404);
        $this->get(self::BASE.'/'.$factura['id'].'/pdf')->assertStatus(404);
    }
}
