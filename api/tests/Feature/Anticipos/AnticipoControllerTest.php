<?php

namespace Tests\Feature\Anticipos;

use App\Contabilidad\Enums\NaturalezaDocumento;
use App\Contabilidad\Models\DocumentoTercero;
use App\Contabilidad\Services\DocumentoTerceroService;
use App\Core\Dinero\Dinero;
use App\Empresas\Models\Empresa;
use App\Terceros\Models\Tercero;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesContabilidadFixtures;
use Tests\TestCase;

class AnticipoControllerTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesContabilidadFixtures;
    use RefreshDatabase;

    private function datosAnticipo(Tercero $tercero): array
    {
        return [
            'tercero_id' => $tercero->id,
            'fecha' => '2026-10-09',
            'numero_documento' => 'ANT-'.uniqid(),
            'concepto' => 'Anticipo de prueba',
            'valor' => '500000',
            'cuenta_anticipo_id' => $this->crearCuenta()->id,
            'cuenta_caja_id' => $this->crearCuenta()->id,
            'tipo_comprobante_id' => $this->crearTipoComprobante()->id,
        ];
    }

    /** Documento por pagar o por cobrar "real" del mismo tercero, listo para cruzarse contra un anticipo. */
    private function documentoReal(Tercero $tercero, NaturalezaDocumento $naturaleza, string $valor = '500000'): DocumentoTercero
    {
        return app(DocumentoTerceroService::class)->crearDocumento(
            $tercero,
            $naturaleza,
            'factura',
            'DOC-'.uniqid(),
            '2026-10-09',
            'Documento real',
            Dinero::desdePesos($valor),
            'pruebas',
            1,
            planCuentaId: $this->crearCuenta()->id,
        );
    }

    public function test_sin_autenticar_no_registra_anticipo(): void
    {
        $this->postJson('/api/v1/anticipos/proveedores', [])->assertStatus(401);
        $this->postJson('/api/v1/anticipos/clientes', [])->assertStatus(401);
        $this->postJson('/api/v1/anticipos/cruzar', [])->assertStatus(401);
    }

    public function test_sin_permiso_no_registra_anticipo(): void
    {
        $admin = $this->actingAsAdmin();
        $tercero = $this->crearTercero();
        $this->actingAsAsesor(Empresa::findOrFail($admin->empresa_id));

        $this->postJson('/api/v1/anticipos/proveedores', $this->datosAnticipo($tercero))->assertStatus(403);
    }

    public function test_valida_campos_obligatorios(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/anticipos/proveedores', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['tercero_id', 'fecha', 'numero_documento', 'concepto', 'valor', 'cuenta_anticipo_id', 'cuenta_caja_id', 'tipo_comprobante_id']);
    }

    public function test_registra_anticipo_a_proveedor_con_el_asiento_correcto(): void
    {
        $this->actingAsAdmin();
        $tercero = $this->crearTercero();
        $datos = $this->datosAnticipo($tercero);

        $respuesta = $this->postJson('/api/v1/anticipos/proveedores', $datos)->assertStatus(201);

        $this->assertSame('anticipo_proveedor', $respuesta->json('anticipo.naturaleza'));
        $this->assertSame('500000.00', $respuesta->json('anticipo.saldo'));
        $this->assertDatabaseHas('documentos_tercero', [
            'tercero_id' => $tercero->id,
            'naturaleza' => 'anticipo_proveedor',
            'numero_documento' => $datos['numero_documento'],
            'saldo' => 50000000,
            'estado' => 'pendiente',
        ]);

        $asientoDebito = DB::table('asientos_contables')->where('cuenta_id', $datos['cuenta_anticipo_id'])->first();
        $this->assertEquals(50000000, $asientoDebito->debito);
        $asientoCredito = DB::table('asientos_contables')->where('cuenta_id', $datos['cuenta_caja_id'])->first();
        $this->assertEquals(50000000, $asientoCredito->credito);
    }

    public function test_registra_anticipo_de_cliente_con_el_asiento_invertido(): void
    {
        $this->actingAsAdmin();
        $tercero = $this->crearTercero();
        $datos = $this->datosAnticipo($tercero);

        $respuesta = $this->postJson('/api/v1/anticipos/clientes', $datos)->assertStatus(201);

        $this->assertSame('anticipo_cliente', $respuesta->json('anticipo.naturaleza'));

        $asientoDebito = DB::table('asientos_contables')->where('cuenta_id', $datos['cuenta_caja_id'])->first();
        $this->assertEquals(50000000, $asientoDebito->debito);
        $asientoCredito = DB::table('asientos_contables')->where('cuenta_id', $datos['cuenta_anticipo_id'])->first();
        $this->assertEquals(50000000, $asientoCredito->credito);
    }

    public function test_cruza_anticipo_a_proveedor_contra_una_cuenta_por_pagar_real(): void
    {
        $this->actingAsAdmin();
        $tercero = $this->crearTercero();
        $anticipo = $this->postJson('/api/v1/anticipos/proveedores', $this->datosAnticipo($tercero))->assertStatus(201)->json('anticipo');
        $documento = $this->documentoReal($tercero, NaturalezaDocumento::PorPagar, '300000');

        $respuesta = $this->postJson('/api/v1/anticipos/cruzar', [
            'anticipo_id' => $anticipo['id'],
            'documento_id' => $documento->id,
            'valor' => '300000',
            'fecha' => '2026-10-09',
            'tipo_comprobante_id' => $this->crearTipoComprobante()->id,
        ])->assertStatus(201);

        $this->assertSame('200000.00', $respuesta->json('anticipo.saldo'));
        $this->assertSame('abonado_parcial', $respuesta->json('anticipo.estado'));
        $this->assertSame('0.00', $respuesta->json('documento.saldo'));
        $this->assertSame('cancelado', $respuesta->json('documento.estado'));
    }

    public function test_cruza_anticipo_de_cliente_contra_una_cuenta_por_cobrar_real(): void
    {
        $this->actingAsAdmin();
        $tercero = $this->crearTercero();
        $anticipo = $this->postJson('/api/v1/anticipos/clientes', $this->datosAnticipo($tercero))->assertStatus(201)->json('anticipo');
        $documento = $this->documentoReal($tercero, NaturalezaDocumento::PorCobrar, '500000');

        $respuesta = $this->postJson('/api/v1/anticipos/cruzar', [
            'anticipo_id' => $anticipo['id'],
            'documento_id' => $documento->id,
            'valor' => '500000',
            'fecha' => '2026-10-09',
            'tipo_comprobante_id' => $this->crearTipoComprobante()->id,
        ])->assertStatus(201);

        $this->assertSame('0.00', $respuesta->json('anticipo.saldo'));
        $this->assertSame('cancelado', $respuesta->json('anticipo.estado'));
        $this->assertSame('0.00', $respuesta->json('documento.saldo'));
    }

    public function test_rechaza_cruzar_anticipo_de_proveedor_contra_una_cuenta_por_cobrar(): void
    {
        $this->actingAsAdmin();
        $tercero = $this->crearTercero();
        $anticipo = $this->postJson('/api/v1/anticipos/proveedores', $this->datosAnticipo($tercero))->assertStatus(201)->json('anticipo');
        $documento = $this->documentoReal($tercero, NaturalezaDocumento::PorCobrar);

        $this->postJson('/api/v1/anticipos/cruzar', [
            'anticipo_id' => $anticipo['id'],
            'documento_id' => $documento->id,
            'valor' => '100000',
            'fecha' => '2026-10-09',
            'tipo_comprobante_id' => $this->crearTipoComprobante()->id,
        ])->assertStatus(422)->assertJsonValidationErrors('documento_id');
    }

    public function test_rechaza_cruzar_documentos_de_terceros_distintos(): void
    {
        $this->actingAsAdmin();
        $tercero = $this->crearTercero();
        $otroTercero = $this->crearTercero();
        $anticipo = $this->postJson('/api/v1/anticipos/proveedores', $this->datosAnticipo($tercero))->assertStatus(201)->json('anticipo');
        $documento = $this->documentoReal($otroTercero, NaturalezaDocumento::PorPagar);

        $this->postJson('/api/v1/anticipos/cruzar', [
            'anticipo_id' => $anticipo['id'],
            'documento_id' => $documento->id,
            'valor' => '100000',
            'fecha' => '2026-10-09',
            'tipo_comprobante_id' => $this->crearTipoComprobante()->id,
        ])->assertStatus(422)->assertJsonValidationErrors('documento_id');
    }

    public function test_rechaza_cruzar_un_valor_mayor_al_saldo_del_anticipo(): void
    {
        $this->actingAsAdmin();
        $tercero = $this->crearTercero();
        $anticipo = $this->postJson('/api/v1/anticipos/proveedores', $this->datosAnticipo($tercero))->assertStatus(201)->json('anticipo');
        $documento = $this->documentoReal($tercero, NaturalezaDocumento::PorPagar, '900000');

        $this->postJson('/api/v1/anticipos/cruzar', [
            'anticipo_id' => $anticipo['id'],
            'documento_id' => $documento->id,
            'valor' => '600000',
            'fecha' => '2026-10-09',
            'tipo_comprobante_id' => $this->crearTipoComprobante()->id,
        ])->assertStatus(422)->assertJsonValidationErrors('valor');
    }

    public function test_un_anticipo_o_documento_de_otra_empresa_responde_422(): void
    {
        $admin = $this->actingAsAdmin();
        $tercero = $this->crearTercero();
        $anticipo = $this->postJson('/api/v1/anticipos/proveedores', $this->datosAnticipo($tercero))->assertStatus(201)->json('anticipo');

        $this->actingAsAdmin();
        $terceroAjeno = $this->crearTercero();
        $documentoAjeno = $this->documentoReal($terceroAjeno, NaturalezaDocumento::PorPagar);

        $this->actingAs($admin, 'sanctum');

        $this->postJson('/api/v1/anticipos/cruzar', [
            'anticipo_id' => $anticipo['id'],
            'documento_id' => $documentoAjeno->id,
            'valor' => '100000',
            'fecha' => '2026-10-09',
            'tipo_comprobante_id' => $this->crearTipoComprobante()->id,
        ])->assertStatus(422)->assertJsonValidationErrors('documento_id');
    }
}
