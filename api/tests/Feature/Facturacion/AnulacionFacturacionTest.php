<?php

namespace Tests\Feature\Facturacion;

use App\Contabilidad\Enums\EstadoComprobante;
use App\Contabilidad\Enums\EventoContable;
use App\Contabilidad\Models\Comprobante;
use App\Contabilidad\Models\ReglaContabilizacion;
use App\Empresas\Models\Empresa;
use App\Facturacion\Models\DocumentoFacturacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\AfirmaAuditoria;
use Tests\Concerns\CreatesAuditoriaFixtures;
use Tests\Concerns\CreatesCreditFixtures;
use Tests\Concerns\CreatesFacturacionFixtures;
use Tests\TestCase;

class AnulacionFacturacionTest extends TestCase
{
    use ActsAsAdmin;
    use AfirmaAuditoria;
    use CreatesAuditoriaFixtures;
    use CreatesCreditFixtures;
    use CreatesFacturacionFixtures;
    use RefreshDatabase;

    private const BASE = '/api/v1/facturacion';

    private function factura(string $valor = '1000'): array
    {
        $this->crearReglasFacturacion();
        $credito = $this->crearCredito($this->crearCliente());

        $borrador = $this->postJson(self::BASE.'/documentos', [
            'cliente_id' => $credito->cliente_id,
            'credito_id' => $credito->id,
            'lineas' => [['concepto' => 'INTERES.AMORT.ADM', 'descripcion' => 'Interés administrativo', 'valor' => $valor, 'tipo_concepto' => 'otros_gastos']],
        ])->assertStatus(201)->json('documento');

        return $this->postJson(self::BASE.'/documentos/'.$borrador['id'].'/emitir')->assertOk()->json('documento');
    }

    /** Aprueba una nota crédito pendiente con una persona distinta de quien la solicitó (7.1.2), y deja a esa persona como usuario autenticado. */
    private function aprobarNotaCredito(array $notaPendiente, Empresa $empresa): array
    {
        $this->actingAsRolConPermisos('aprobador_nc', [
            'facturacion.aprobar_nota_credito', 'facturacion.listar_facturas', 'facturacion.listar_notas_credito',
        ], $empresa);

        return $this->postJson(self::BASE.'/documentos/'.$notaPendiente['id'].'/aprobar')->assertOk()->json('documento');
    }

    public function test_sin_autenticar_y_sin_permiso_no_anula(): void
    {
        $this->postJson(self::BASE.'/documentos/1/anular', ['motivo' => 'x'])->assertStatus(401);

        $admin = $this->actingAsAdmin();
        $factura = $this->factura();
        $this->actingAsAsesor(Empresa::findOrFail($admin->empresa_id));

        $this->postJson(self::BASE.'/documentos/'.$factura['id'].'/anular', ['motivo' => 'x'])->assertStatus(403);
    }

    public function test_anular_de_otra_empresa_responde_404(): void
    {
        $this->actingAsAdmin();
        $factura = $this->factura();

        $this->actingAsAdmin();

        $this->postJson(self::BASE.'/documentos/'.$factura['id'].'/anular', ['motivo' => 'Otra empresa'])->assertStatus(404);
    }

    public function test_anular_exige_motivo(): void
    {
        $this->actingAsAdmin();
        $factura = $this->factura();

        $this->postJson(self::BASE.'/documentos/'.$factura['id'].'/anular', ['motivo' => ''])->assertStatus(422)->assertJsonValidationErrors('motivo');
        $this->assertDatabaseMissing('documentos_facturacion', ['tipo' => 'nota_credito']);
    }

    public function test_anula_con_nota_credito_conserva_el_numero_y_deja_el_reverso_enlazado(): void
    {
        $this->actingAsAdmin();
        $factura = $this->factura('1000');
        $asientoFactura = DocumentoFacturacion::findOrFail($factura['id'])->comprobante_id;

        $respuesta = $this->postJson(self::BASE.'/documentos/'.$factura['id'].'/anular', ['motivo' => 'Error en el valor'])->assertOk();

        $this->assertSame('anulada', $respuesta->json('totales.situacion'));
        $this->assertTrue($respuesta->json('totales.anulada'));
        $this->assertSame('0.00', $respuesta->json('totales.saldo'));

        $nota = DocumentoFacturacion::query()->where('documento_origen_id', $factura['id'])->firstOrFail();

        $this->assertSame('nota_credito', $nota->tipo->value);
        $this->assertSame('Error en el valor', $nota->motivo);
        $this->assertDatabaseHas('documentos_facturacion', ['id' => $factura['id'], 'numero' => $factura['numero'], 'estado' => 'emitido']);

        $asiento = Comprobante::findOrFail($nota->comprobante_id);
        $regla = ReglaContabilizacion::query()->where('evento', EventoContable::NotaCreditoFactura)->firstOrFail();

        $this->assertSame(EstadoComprobante::Contabilizado, $asiento->estado);
        $this->assertSame(100000, $asiento->total_debito->centavos());
        $this->assertTrue($asiento->total_debito->igualA($asiento->total_credito));
        $this->assertSame($regla->cuenta_debito_id, $asiento->lineas->firstWhere(fn ($linea) => $linea->debito->esPositivo())->cuenta_id);

        // El asiento original no se reversa ni se edita: el reverso es el asiento de la nota, enlazado por la factura.
        $this->assertSame($asientoFactura, DocumentoFacturacion::findOrFail($factura['id'])->comprobante_id);
        $this->assertSame(EstadoComprobante::Contabilizado, Comprobante::findOrFail($asientoFactura)->estado);

        $evento = $this->assertEventoAuditoria('anular', 'facturacion', 'documento_facturacion', $nota->id);
        $this->assertSame('Error en el valor', $evento['motivo']);
    }

    public function test_no_anula_con_cobros_vigentes_ni_con_notas_debito_ni_dos_veces(): void
    {
        $this->actingAsAdmin();
        $factura = $this->factura('1000');

        $cobro = $this->postJson(self::BASE.'/documentos/'.$factura['id'].'/cobros', ['fecha' => '2026-10-05', 'valor' => '300', 'medio' => 'efectivo'])
            ->assertStatus(201)->json('cobro');

        $this->postJson(self::BASE.'/documentos/'.$factura['id'].'/anular', ['motivo' => 'Con cobro'])->assertStatus(422)->assertJsonValidationErrors('documento');

        $this->postJson(self::BASE.'/cobros/'.$cobro['id'].'/reversar', ['motivo' => 'Se reversa el cobro'])->assertOk();
        $this->postJson(self::BASE.'/documentos/'.$factura['id'].'/notas', [
            'tipo' => 'nota_debito', 'motivo_codigo' => 'gastos_por_cobrar', 'fecha_vencimiento' => '2026-12-01',
            'lineas' => [['concepto' => 'MORA', 'descripcion' => 'Mora', 'valor' => '50']],
        ])->assertStatus(201);

        $this->postJson(self::BASE.'/documentos/'.$factura['id'].'/anular', ['motivo' => 'Con nota débito'])->assertStatus(422)->assertJsonValidationErrors('documento');
    }

    public function test_una_factura_anulada_no_se_anula_otra_vez_ni_admite_notas_debito(): void
    {
        $this->actingAsAdmin();
        $factura = $this->factura('1000');

        $this->postJson(self::BASE.'/documentos/'.$factura['id'].'/anular', ['motivo' => 'Primera vez'])->assertOk();

        $this->postJson(self::BASE.'/documentos/'.$factura['id'].'/anular', ['motivo' => 'Segunda vez'])->assertStatus(422)->assertJsonValidationErrors('documento');
        $this->postJson(self::BASE.'/documentos/'.$factura['id'].'/notas', [
            'tipo' => 'nota_debito', 'motivo_codigo' => 'gastos_por_cobrar', 'fecha_vencimiento' => '2026-12-01',
            'lineas' => [['concepto' => 'MORA', 'descripcion' => 'Mora', 'valor' => '50']],
        ])->assertStatus(422)->assertJsonValidationErrors('documento');
    }

    public function test_una_nota_credito_tras_un_cobro_parcial_puede_dejar_saldo_a_favor_pero_no_excede_la_linea(): void
    {
        $empresa = $this->crearEmpresaDePrueba();
        $admin = $this->actingAsAdmin($empresa);
        $factura = $this->factura('1000');
        $linea = $factura['lineas'][0]['id'];

        $this->postJson(self::BASE.'/documentos/'.$factura['id'].'/cobros', ['fecha' => '2026-10-05', 'valor' => '300', 'medio' => 'efectivo'])->assertStatus(201);

        // La línea tiene 1000 por acreditar: una nota de 800 no la excede, aunque ya solo quedaban 700 por cobrar.
        $pendiente = $this->postJson(self::BASE.'/documentos/'.$factura['id'].'/notas', [
            'tipo' => 'nota_credito', 'motivo_codigo' => 'rebaja_descuento', 'lineas' => [['linea_origen_id' => $linea, 'valor' => '800']],
        ])->assertStatus(201)->json('documento');
        $this->aprobarNotaCredito($pendiente, $empresa);
        $this->actingAs($admin, 'sanctum');

        $detalle = $this->getJson(self::BASE.'/documentos/'.$factura['id'])->assertOk();

        $this->assertSame('con_saldo_a_favor', $detalle->json('totales.situacion'));
        $this->assertSame('-100.00', $detalle->json('totales.saldo'));
        $this->assertSame('100.00', $detalle->json('totales.saldo_a_favor'));

        // Pero no puede exceder lo que queda de la línea (200 = 1000 - 800 ya acreditados).
        $this->postJson(self::BASE.'/documentos/'.$factura['id'].'/notas', [
            'tipo' => 'nota_credito', 'motivo_codigo' => 'rebaja_descuento', 'lineas' => [['linea_origen_id' => $linea, 'valor' => '300']],
        ])->assertStatus(422)->assertJsonValidationErrors('lineas');
        $this->assertFalse($detalle->json('totales.anulada'));
    }
}
