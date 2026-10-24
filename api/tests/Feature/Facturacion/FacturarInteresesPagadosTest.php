<?php

namespace Tests\Feature\Facturacion;

use App\Creditos\Enums\EstadoCuota;
use App\Creditos\Models\Credito;
use App\Creditos\Models\Cuota;
use App\Facturacion\Models\AplicacionInteresFacturado;
use App\Pagos\Enums\FormaPago;
use App\Pagos\Models\Pago;
use App\Pagos\Models\PagoDetalle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesCreditFixtures;
use Tests\Concerns\CreatesFacturacionFixtures;
use Tests\TestCase;

class FacturarInteresesPagadosTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesCreditFixtures;
    use CreatesFacturacionFixtures;
    use RefreshDatabase;

    private const BASE = '/api/v1/facturacion/documentos/intereses-pagados';

    private function credito(): Credito
    {
        $this->crearReglasFacturacion();

        return $this->crearCredito($this->crearCliente());
    }

    /** Cuota pagada con un solo pago (un `pago_detalle`) que cubrió exactamente capital + interés. */
    private function cuotaPagadaEnUnPago(Credito $credito, int $interes, int $capital = 250, int $numeroCuota = 1): Cuota
    {
        $cuota = $this->crearCuota($credito, [
            'numero_cuota' => $numeroCuota,
            'valor_capital' => $capital,
            'valor_interes' => $interes,
            'valor_otros_gastos' => 0,
            'valor_pagado' => $capital + $interes,
            'estado' => EstadoCuota::Pagada,
        ]);

        $pago = Pago::create([
            'credito_id' => $credito->id,
            'cliente_id' => $credito->cliente_id,
            'valor_pago' => $capital + $interes,
            'fecha_pago' => '2026-02-01',
            'forma_pago' => FormaPago::Efectivo,
        ]);

        PagoDetalle::create(['pago_id' => $pago->id, 'cuota_id' => $cuota->id, 'valor_aplicado' => $capital + $interes]);

        return $cuota;
    }

    public function test_sin_autenticar_recibe_401(): void
    {
        $this->postJson(self::BASE, ['credito_id' => 1, 'cuota_ids' => [1]])->assertStatus(401);
    }

    public function test_sin_permiso_recibe_403(): void
    {
        $this->actingAsAsesor();

        $this->postJson(self::BASE, ['credito_id' => 1, 'cuota_ids' => [1]])->assertStatus(403);
    }

    public function test_una_cuota_no_pagada_responde_422(): void
    {
        $this->actingAsAdmin();
        $credito = $this->credito();
        $cuota = $this->crearCuota($credito, ['estado' => EstadoCuota::Pendiente, 'valor_interes' => 30]);

        // El `Rule::exists` del Request solo valida que la cuota exista y pertenezca al crédito, no su estado:
        // "no pagada" lo rechaza el Service, con la clave `cuota_ids` sin índice (no `cuota_ids.0`).
        $this->postJson(self::BASE, ['credito_id' => $credito->id, 'cuota_ids' => [$cuota->id]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('cuota_ids');
    }

    public function test_una_cuota_de_otro_credito_responde_422(): void
    {
        $this->actingAsAdmin();
        $credito = $this->credito();
        $otroCredito = $this->crearCredito($this->crearCliente());
        $cuotaAjena = $this->cuotaPagadaEnUnPago($otroCredito, 30);

        $this->postJson(self::BASE, ['credito_id' => $credito->id, 'cuota_ids' => [$cuotaAjena->id]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('cuota_ids.0');
    }

    public function test_factura_el_interes_de_una_cuota_pagada_y_queda_cobrada(): void
    {
        $this->actingAsAdmin();
        $credito = $this->credito();
        $cuota = $this->cuotaPagadaEnUnPago($credito, interes: 30);

        $respuesta = $this->postJson(self::BASE, ['credito_id' => $credito->id, 'cuota_ids' => [$cuota->id]])->assertStatus(201);

        $documento = $respuesta->json('documento');
        $this->assertCount(1, $documento['lineas']);
        $this->assertSame('interes_corriente', $documento['lineas'][0]['tipo_concepto']);
        $this->assertSame($cuota->id, $documento['lineas'][0]['cuota_id']);
        $this->assertSame('30.00', $documento['lineas'][0]['valor']);

        $respuesta->assertJsonPath('totales.saldo', '0.00');
        $respuesta->assertJsonPath('totales.situacion', 'pagada');

        $this->assertDatabaseHas('documentos_tercero', ['numero_documento' => $documento['numero'], 'saldo' => 0]);
        // La CxC que causa la emisión y la que la cruza con el interés recaudado llevan la factura como documento cruce.
        $this->assertDatabaseHas('asientos_contables', ['documento_cruce' => $documento['numero'], 'debito' => 3000]);
        $this->assertDatabaseHas('asientos_contables', ['documento_cruce' => $documento['numero'], 'credito' => 3000]);

        $pagoDetalle = PagoDetalle::where('cuota_id', $cuota->id)->first();
        $aplicacion = AplicacionInteresFacturado::where('pago_detalle_id', $pagoDetalle->id)->first();
        $this->assertNotNull($aplicacion);
        $this->assertSame($documento['lineas'][0]['id'], $aplicacion->linea_documento_facturacion_id);
        $this->assertSame(3000, $aplicacion->getRawOriginal('valor'));
    }

    public function test_factura_varias_cuotas_pagadas_en_un_solo_envio(): void
    {
        $this->actingAsAdmin();
        $credito = $this->credito();
        $cuota1 = $this->cuotaPagadaEnUnPago($credito, interes: 30, numeroCuota: 1);
        $cuota2 = $this->cuotaPagadaEnUnPago($credito, interes: 20, numeroCuota: 2);

        $documento = $this->postJson(self::BASE, ['credito_id' => $credito->id, 'cuota_ids' => [$cuota1->id, $cuota2->id]])
            ->assertStatus(201)
            ->json('documento');

        $this->assertCount(2, $documento['lineas']);
        $valores = collect($documento['lineas'])->pluck('cuota_id', 'valor');
        $this->assertSame($cuota1->id, $valores['30.00']);
        $this->assertSame($cuota2->id, $valores['20.00']);
    }

    public function test_reparte_el_interes_entre_los_pagos_que_lo_cubrieron(): void
    {
        $this->actingAsAdmin();
        $credito = $this->credito();
        $cuota = $this->crearCuota($credito, [
            'numero_cuota' => 1,
            'valor_capital' => 250,
            'valor_interes' => 30,
            'valor_otros_gastos' => 0,
            'valor_pagado' => 280,
            'estado' => EstadoCuota::Pagada,
        ]);

        // Primer abono: 20, todo a interés (interés tiene prioridad). Segundo abono: 260, los 10 de interés
        // restantes más los 250 de capital.
        $pago1 = Pago::create(['credito_id' => $credito->id, 'cliente_id' => $credito->cliente_id, 'valor_pago' => 20, 'fecha_pago' => '2026-02-01', 'forma_pago' => FormaPago::Efectivo]);
        $pago2 = Pago::create(['credito_id' => $credito->id, 'cliente_id' => $credito->cliente_id, 'valor_pago' => 260, 'fecha_pago' => '2026-02-15', 'forma_pago' => FormaPago::Efectivo]);
        $detalle1 = PagoDetalle::create(['pago_id' => $pago1->id, 'cuota_id' => $cuota->id, 'valor_aplicado' => 20]);
        $detalle2 = PagoDetalle::create(['pago_id' => $pago2->id, 'cuota_id' => $cuota->id, 'valor_aplicado' => 260]);

        $documento = $this->postJson(self::BASE, ['credito_id' => $credito->id, 'cuota_ids' => [$cuota->id]])
            ->assertStatus(201)
            ->json('documento');

        $lineaId = $documento['lineas'][0]['id'];
        $aplicaciones = AplicacionInteresFacturado::where('linea_documento_facturacion_id', $lineaId)->orderBy('pago_detalle_id')->get();

        $this->assertCount(2, $aplicaciones);
        $this->assertSame($detalle1->id, $aplicaciones[0]->pago_detalle_id);
        $this->assertSame(2000, $aplicaciones[0]->getRawOriginal('valor'));
        $this->assertSame($detalle2->id, $aplicaciones[1]->pago_detalle_id);
        $this->assertSame(1000, $aplicaciones[1]->getRawOriginal('valor'));
    }

    public function test_tercero_sin_datos_fiscales_responde_422_sin_dejar_nada_guardado(): void
    {
        $this->actingAsAdmin();
        $this->crearReglasFacturacion();
        $credito = $this->crearCredito($this->crearCliente(['direccion' => '']));
        $cuota = $this->cuotaPagadaEnUnPago($credito, interes: 30);

        $this->postJson(self::BASE, ['credito_id' => $credito->id, 'cuota_ids' => [$cuota->id]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('documento');

        $this->assertDatabaseCount('documentos_facturacion', 0);
        $this->assertDatabaseCount('aplicaciones_interes_facturado', 0);
    }

    public function test_no_vuelve_a_facturar_una_cuota_ya_facturada(): void
    {
        $this->actingAsAdmin();
        $credito = $this->credito();
        $cuota = $this->cuotaPagadaEnUnPago($credito, interes: 30);

        $this->postJson(self::BASE, ['credito_id' => $credito->id, 'cuota_ids' => [$cuota->id]])->assertStatus(201);

        $this->postJson(self::BASE, ['credito_id' => $credito->id, 'cuota_ids' => [$cuota->id]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('cuota_ids');
    }

    public function test_un_credito_de_otra_empresa_responde_422(): void
    {
        $this->actingAsAdmin();
        $creditoAjeno = $this->credito();

        $this->actingAsAdmin();

        $this->postJson(self::BASE, ['credito_id' => $creditoAjeno->id, 'cuota_ids' => [1]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('credito_id');
    }
}
