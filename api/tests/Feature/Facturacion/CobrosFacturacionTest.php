<?php

namespace Tests\Feature\Facturacion;

use App\Contabilidad\Enums\EstadoComprobante;
use App\Contabilidad\Enums\EventoContable;
use App\Contabilidad\Models\Comprobante;
use App\Contabilidad\Models\ReglaContabilizacion;
use App\Empresas\Models\Empresa;
use App\Facturacion\Models\CobroFacturacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use LogicException;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\AfirmaAuditoria;
use Tests\Concerns\CreatesAuditoriaFixtures;
use Tests\Concerns\CreatesCreditFixtures;
use Tests\Concerns\CreatesFacturacionFixtures;
use Tests\TestCase;

class CobrosFacturacionTest extends TestCase
{
    use ActsAsAdmin;
    use AfirmaAuditoria;
    use CreatesAuditoriaFixtures;
    use CreatesCreditFixtures;
    use CreatesFacturacionFixtures;
    use RefreshDatabase;

    private const BASE = '/api/v1/facturacion';

    /** Factura emitida de $valor pesos, con las reglas de contabilización configuradas en la empresa del usuario actual. */
    private function factura(string $valor = '1000'): array
    {
        $this->crearReglasFacturacion();
        $credito = $this->crearCredito($this->crearCliente());

        $borrador = $this->postJson(self::BASE.'/documentos', [
            'credito_id' => $credito->id,
            'lineas' => [['concepto' => 'INTERES.AMORT.ADM', 'descripcion' => 'Interés administrativo', 'valor' => $valor]],
        ])->assertStatus(201)->json('documento');

        return $this->postJson(self::BASE.'/documentos/'.$borrador['id'].'/emitir')->assertOk()->json('documento');
    }

    /** @param array<string, mixed> $datos */
    private function cobrar(array $factura, array $datos = [], string $valor = '400'): TestResponse
    {
        return $this->postJson(self::BASE.'/documentos/'.$factura['id'].'/cobros', array_merge([
            'fecha' => '2026-10-05',
            'valor' => $valor,
            'medio' => 'transferencia',
            'referencia' => 'REF-1',
        ], $datos));
    }

    public function test_sin_autenticar_recibe_401(): void
    {
        $this->postJson(self::BASE.'/documentos/1/cobros', [])->assertStatus(401);
        $this->postJson(self::BASE.'/cobros/1/reversar', [])->assertStatus(401);
    }

    public function test_asesor_sin_permiso_recibe_403(): void
    {
        $admin = $this->actingAsAdmin();
        $factura = $this->factura();

        $this->actingAsAsesor(Empresa::findOrFail($admin->empresa_id));

        $this->postJson(self::BASE.'/documentos/'.$factura['id'].'/cobros', [])->assertStatus(403);
    }

    public function test_un_cobro_o_una_factura_de_otra_empresa_responde_404(): void
    {
        $this->actingAsAdmin();
        $factura = $this->factura();
        $cobro = $this->cobrar($factura)->assertStatus(201)->json('cobro');

        $this->actingAsAdmin();

        $this->postJson(self::BASE.'/documentos/'.$factura['id'].'/cobros', ['fecha' => '2026-10-05', 'valor' => '10', 'medio' => 'efectivo'])->assertStatus(404);
        $this->postJson(self::BASE.'/cobros/'.$cobro['id'].'/reversar', ['motivo' => 'Otra empresa'])->assertStatus(404);
    }

    public function test_rechaza_un_cobro_mayor_al_saldo_y_datos_invalidos(): void
    {
        $this->actingAsAdmin();
        $factura = $this->factura('1000');

        $this->cobrar($factura, [], '1500')->assertStatus(422)->assertJsonValidationErrors('valor');
        $this->cobrar($factura, ['medio' => 'bitcoin'])->assertStatus(422)->assertJsonValidationErrors('medio');
        $this->cobrar($factura, ['fecha' => '05/10/2026'])->assertStatus(422)->assertJsonValidationErrors('fecha');
        $this->assertDatabaseCount('cobros_facturacion', 0);
    }

    public function test_cobro_parcial_y_luego_total_derivan_la_situacion_y_cuadran_el_asiento(): void
    {
        $this->actingAsAdmin();
        $factura = $this->factura('1000');

        $parcial = $this->cobrar($factura, [], '400')->assertStatus(201);

        $this->assertSame('parcialmente_pagada', $parcial->json('totales.situacion'));
        $this->assertSame('600.00', $parcial->json('totales.saldo'));
        $this->assertDatabaseHas('cobros_facturacion', [
            'documento_id' => $factura['id'], 'tipo' => 'cobro', 'valor' => 40000, 'medio' => 'transferencia', 'referencia' => 'REF-1', 'motivo' => null,
        ]);

        $cobro = CobroFacturacion::query()->where('documento_id', $factura['id'])->firstOrFail();
        $asiento = Comprobante::findOrFail($cobro->comprobante_id);

        $this->assertSame(EstadoComprobante::Contabilizado, $asiento->estado);
        $this->assertSame(40000, $asiento->total_debito->centavos());
        $this->assertTrue($asiento->total_debito->igualA($asiento->total_credito));
        $this->assertSame('facturacion_cobro', $asiento->modulo_origen);

        $regla = ReglaContabilizacion::query()->where('evento', EventoContable::CobroFactura)->firstOrFail();
        $debito = $asiento->lineas->firstWhere(fn ($linea) => $linea->debito->esPositivo());
        $this->assertSame($regla->cuenta_debito_id, $debito->cuenta_id);

        $total = $this->cobrar($factura, [], '600')->assertStatus(201);

        $this->assertSame('pagada', $total->json('totales.situacion'));
        $this->assertSame('0.00', $total->json('totales.saldo'));
        $this->cobrar($factura, [], '1')->assertStatus(422)->assertJsonValidationErrors('valor');

        $cobrosAuditados = array_filter($this->eventosDeAuditoria('crear', 'facturacion'), fn (array $evento) => $evento['entidad'] === 'cobro_facturacion');
        $this->assertCount(2, $cobrosAuditados);
    }

    public function test_reversar_un_cobro_anula_su_asiento_sin_borrarlo_y_no_se_repite(): void
    {
        $this->actingAsAdmin();
        $factura = $this->factura('1000');
        $cobro = $this->cobrar($factura, [], '400')->assertStatus(201)->json('cobro');

        $original = Comprobante::findOrFail(CobroFacturacion::findOrFail($cobro['id'])->comprobante_id);

        $respuesta = $this->postJson(self::BASE.'/cobros/'.$cobro['id'].'/reversar', ['motivo' => 'Consignación duplicada'])->assertOk();

        $this->assertSame('emitida', $respuesta->json('totales.situacion'));
        $this->assertSame('1000.00', $respuesta->json('totales.saldo'));
        $this->assertDatabaseHas('cobros_facturacion', [
            'tipo' => 'reversa', 'cobro_origen_id' => $cobro['id'], 'valor' => 40000, 'motivo' => 'Consignación duplicada',
        ]);
        $this->assertDatabaseHas('cobros_facturacion', ['id' => $cobro['id'], 'tipo' => 'cobro', 'valor' => 40000]);

        $original->refresh();
        $this->assertSame(EstadoComprobante::Anulado, $original->estado);
        $this->assertNotNull($original->comprobante_reverso_id);

        $reverso = Comprobante::findOrFail($original->comprobante_reverso_id);
        $this->assertTrue($reverso->total_debito->igualA($reverso->total_credito));
        $this->assertSame(40000, $reverso->total_debito->centavos());

        $this->postJson(self::BASE.'/cobros/'.$cobro['id'].'/reversar', ['motivo' => 'Otra vez'])->assertStatus(422)->assertJsonValidationErrors('cobro');
        $this->assertDatabaseCount('cobros_facturacion', 2);
    }

    public function test_una_reversa_no_se_reversa_y_el_motivo_es_obligatorio(): void
    {
        $this->actingAsAdmin();
        $factura = $this->factura('1000');
        $cobro = $this->cobrar($factura, [], '400')->assertStatus(201)->json('cobro');

        $this->postJson(self::BASE.'/cobros/'.$cobro['id'].'/reversar', ['motivo' => ''])->assertStatus(422)->assertJsonValidationErrors('motivo');

        $this->postJson(self::BASE.'/cobros/'.$cobro['id'].'/reversar', ['motivo' => 'Error'])->assertOk();
        $reversa = CobroFacturacion::query()->where('cobro_origen_id', $cobro['id'])->firstOrFail();

        $this->postJson(self::BASE.'/cobros/'.$reversa->id.'/reversar', ['motivo' => 'Reversar la reversa'])->assertStatus(422)->assertJsonValidationErrors('cobro');
    }

    public function test_un_cobro_registrado_no_se_edita_ni_se_borra(): void
    {
        $this->actingAsAdmin();
        $factura = $this->factura('1000');
        $this->cobrar($factura, [], '400')->assertStatus(201);

        $cobro = CobroFacturacion::query()->firstOrFail();

        $this->expectException(LogicException::class);
        $cobro->update(['valor' => 1]);
    }

    public function test_un_cobro_no_se_puede_eliminar(): void
    {
        $this->actingAsAdmin();
        $factura = $this->factura('1000');
        $this->cobrar($factura, [], '400')->assertStatus(201);

        $this->expectException(LogicException::class);
        CobroFacturacion::query()->firstOrFail()->delete();
    }
}
