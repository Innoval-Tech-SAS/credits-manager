<?php

namespace Tests\Feature\Egresos;

use App\Contabilidad\Models\DocumentoTercero;
use App\Empresas\Models\Empresa;
use App\Gastos\Models\Gasto;
use App\Gastos\Services\GastoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesContabilidadFixtures;
use Tests\Concerns\CreatesGastosFixtures;
use Tests\TestCase;

class EgresoControllerTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesContabilidadFixtures;
    use CreatesGastosFixtures;
    use RefreshDatabase;

    private const BASE = '/api/v1/egresos';

    /** Gasto causado a un tercero: queda con su documento por pagar (saldo = valor neto) listo para pagarse. */
    private function gastoConDocumentoPendiente(array $overrides = []): Gasto
    {
        $tipoGasto = $this->crearTipoGasto();
        $this->crearParametrizacion($tipoGasto);
        $this->crearValorUvt();
        $centroCosto = $this->crearCentroCosto();
        $tercero = $this->crearTerceroFiscal();

        $gasto = app(GastoService::class)->causar(array_merge([
            'tipo_gasto_id' => $tipoGasto->id,
            'tercero_id' => $tercero->id,
            'centro_costo_id' => $centroCosto->id,
            'fecha' => '2026-01-15',
            'numero_documento' => 'FC-001',
            'concepto' => 'Compra de insumos',
            'valor_bruto' => '1000',
        ], $overrides));

        return $gasto->fresh(['documentoTercero']);
    }

    public function test_sin_autenticar_no_registra(): void
    {
        $this->postJson(self::BASE, [])->assertStatus(401);
    }

    public function test_sin_permiso_no_registra(): void
    {
        $admin = $this->actingAsAdmin();
        $gasto = $this->gastoConDocumentoPendiente();
        $this->actingAsAsesor(Empresa::findOrFail($admin->empresa_id));

        $this->postJson(self::BASE, $this->datosValidos($gasto))->assertStatus(403);
    }

    public function test_valida_los_campos_requeridos(): void
    {
        $this->actingAsAdmin();

        $this->postJson(self::BASE, [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['tercero_id', 'tipo_comprobante_id', 'fecha', 'medio_pago', 'cuenta_caja_id', 'concepto', 'aplicaciones']);
    }

    public function test_rechaza_un_documento_que_no_pertenece_al_tercero_enviado(): void
    {
        $this->actingAsAdmin();
        $gasto = $this->gastoConDocumentoPendiente();
        $otroTercero = $this->crearTerceroFiscal();

        $datos = $this->datosValidos($gasto);
        $datos['tercero_id'] = $otroTercero->id;

        $this->postJson(self::BASE, $datos)->assertStatus(422)->assertJsonValidationErrors('aplicaciones');
    }

    public function test_registra_el_egreso_cruza_el_documento_y_lo_contabiliza(): void
    {
        $this->actingAsAdmin();
        $gasto = $this->gastoConDocumentoPendiente();

        $respuesta = $this->postJson(self::BASE, $this->datosValidos($gasto))->assertStatus(201);

        $this->assertSame('CE 1', $respuesta->json('egreso.numero'));
        $this->assertSame('1000.00', $respuesta->json('egreso.valor'));

        $documento = DocumentoTercero::findOrFail($gasto->documento_tercero_id);
        $this->assertEquals(0, $documento->saldo->aFloat());
        $this->assertSame('cancelado', $documento->estado->value);

        $egresoId = $respuesta->json('egreso.id');
        $this->assertDatabaseHas('aplicaciones_documento', [
            'documento_tercero_id' => $documento->id,
            'modulo_aplicacion' => 'egresos',
            'aplicacion_id' => $egresoId,
            'valor_aplicado' => 100000,
        ]);

        $comprobanteId = $respuesta->json('egreso.comprobante_id');
        $this->assertNotNull($comprobanteId);
        $this->assertDatabaseHas('asientos_contables', ['comprobante_id' => $comprobanteId, 'cuenta_id' => $gasto->parametrizacion->cuenta_cxp_id]);
    }

    public function test_un_tercero_de_otra_empresa_responde_422(): void
    {
        $admin = $this->actingAsAdmin();
        $gasto = $this->gastoConDocumentoPendiente();

        $this->actingAsAdmin();
        $terceroAjeno = $this->crearTerceroFiscal();

        $this->actingAs($admin, 'sanctum');

        $datos = $this->datosValidos($gasto);
        $datos['tercero_id'] = $terceroAjeno->id;

        $this->postJson(self::BASE, $datos)->assertStatus(422)->assertJsonValidationErrors('tercero_id');
    }

    private function datosValidos(Gasto $gasto): array
    {
        return [
            'tercero_id' => $gasto->tercero_id,
            'tipo_comprobante_id' => $this->crearTipoComprobante()->id,
            'fecha' => '2026-01-20',
            'medio_pago' => 'transferencia',
            'cuenta_caja_id' => $this->crearCuenta()->id,
            'concepto' => 'Pago de la compra',
            'aplicaciones' => [
                ['documento_tercero_id' => $gasto->documento_tercero_id, 'valor' => '1000'],
            ],
        ];
    }
}
