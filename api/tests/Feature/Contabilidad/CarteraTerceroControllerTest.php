<?php

namespace Tests\Feature\Contabilidad;

use App\Contabilidad\Enums\NaturalezaDocumento;
use App\Contabilidad\Services\DocumentoTerceroService;
use App\Core\Dinero\Dinero;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesContabilidadFixtures;
use Tests\TestCase;

class CarteraTerceroControllerTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesContabilidadFixtures;
    use RefreshDatabase;

    private const BASE = '/api/v1/cartera-terceros';

    public function test_sin_autenticar_no_consulta(): void
    {
        $this->getJson(self::BASE.'?naturaleza=por_pagar')->assertStatus(401);
    }

    public function test_exige_naturaleza(): void
    {
        $this->actingAsAdmin();

        $this->getJson(self::BASE)->assertStatus(422)->assertJsonValidationErrors('naturaleza');
    }

    public function test_lista_documentos_con_vencido_derivado_de_la_fecha_de_corte(): void
    {
        $this->actingAsAdmin();
        $tercero = $this->crearTercero();
        $cuenta = $this->crearCuenta();
        $servicio = app(DocumentoTerceroService::class);

        $servicio->crearDocumento(
            $tercero, NaturalezaDocumento::PorPagar, 'factura', 'FC-001', '2026-01-01', 'Vencida',
            Dinero::desdePesos(1000), 'facturas', 1, planCuentaId: $cuenta->id, fechaVencimiento: '2026-01-10',
        );
        $servicio->crearDocumento(
            $tercero, NaturalezaDocumento::PorPagar, 'factura', 'FC-002', '2026-01-01', 'Vigente',
            Dinero::desdePesos(500), 'facturas', 2, planCuentaId: $cuenta->id, fechaVencimiento: '2026-02-10',
        );

        $respuesta = $this->getJson(self::BASE.'?naturaleza=por_pagar&fecha_corte=2026-01-20')->assertOk();

        $porNumero = collect($respuesta->json('data'))->keyBy('numero_documento');
        $this->assertSame('vencido', $porNumero['FC-001']['estado']);
        $this->assertSame(10, $porNumero['FC-001']['dias_mora']);
        $this->assertSame('pendiente', $porNumero['FC-002']['estado']);
        $this->assertSame(0, $porNumero['FC-002']['dias_mora']);
        $this->assertSame($cuenta->id, $porNumero['FC-002']['plan_cuenta']['id']);
    }

    public function test_filtra_solo_los_vencidos(): void
    {
        $this->actingAsAdmin();
        $tercero = $this->crearTercero();
        $servicio = app(DocumentoTerceroService::class);

        $servicio->crearDocumento($tercero, NaturalezaDocumento::PorCobrar, 'factura', 'F-001', '2026-01-01', 'Vencida', Dinero::desdePesos(1000), 'facturacion', 1, fechaVencimiento: '2026-01-05');
        $servicio->crearDocumento($tercero, NaturalezaDocumento::PorCobrar, 'factura', 'F-002', '2026-01-01', 'Vigente', Dinero::desdePesos(500), 'facturacion', 2, fechaVencimiento: '2026-03-05');

        $respuesta = $this->getJson(self::BASE.'?naturaleza=por_cobrar&estado=vencido&fecha_corte=2026-01-20')->assertOk();

        $this->assertCount(1, $respuesta->json('data'));
        $this->assertSame('F-001', $respuesta->json('data.0.numero_documento'));
    }

    public function test_no_ve_documentos_de_otra_empresa(): void
    {
        $admin = $this->actingAsAdmin();
        $tercero = $this->crearTercero();
        app(DocumentoTerceroService::class)->crearDocumento($tercero, NaturalezaDocumento::PorPagar, 'factura', 'FC-001', '2026-01-01', 'X', Dinero::desdePesos(1000), 'facturas', 1);

        $this->actingAsAdmin();

        $respuesta = $this->getJson(self::BASE.'?naturaleza=por_pagar')->assertOk();
        $this->assertCount(0, $respuesta->json('data'));

        $this->actingAs($admin, 'sanctum');
        $respuesta = $this->getJson(self::BASE.'?naturaleza=por_pagar')->assertOk();
        $this->assertCount(1, $respuesta->json('data'));
    }

    public function test_edades_agrupa_el_saldo_por_tramo_de_mora(): void
    {
        $this->actingAsAdmin();
        $tercero = $this->crearTercero();
        $servicio = app(DocumentoTerceroService::class);

        $servicio->crearDocumento($tercero, NaturalezaDocumento::PorPagar, 'factura', 'FC-001', '2026-01-01', 'Corriente', Dinero::desdePesos(100), 'facturas', 1, fechaVencimiento: '2026-02-01');
        $servicio->crearDocumento($tercero, NaturalezaDocumento::PorPagar, 'factura', 'FC-002', '2026-01-01', '20 días', Dinero::desdePesos(200), 'facturas', 2, fechaVencimiento: '2026-01-01');
        $servicio->crearDocumento($tercero, NaturalezaDocumento::PorPagar, 'factura', 'FC-003', '2026-01-01', '100 días', Dinero::desdePesos(300), 'facturas', 3, fechaVencimiento: '2025-10-13');

        $respuesta = $this->getJson(self::BASE.'/edades?naturaleza=por_pagar&fecha_corte=2026-01-21')->assertOk();

        $this->assertSame('100.00', $respuesta->json('edades.corriente'));
        $this->assertSame('200.00', $respuesta->json('edades.1_30'));
        $this->assertSame('0.00', $respuesta->json('edades.31_60'));
        $this->assertSame('0.00', $respuesta->json('edades.61_90'));
        $this->assertSame('300.00', $respuesta->json('edades.mas_90'));
    }
}
