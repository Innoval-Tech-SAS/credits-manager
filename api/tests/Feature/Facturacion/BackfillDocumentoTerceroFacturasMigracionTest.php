<?php

namespace Tests\Feature\Facturacion;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesCreditFixtures;
use Tests\Concerns\CreatesFacturacionFixtures;
use Tests\TestCase;

/**
 * Emite facturas por el flujo actual (que ya crea su documento_tercero), las deja como estaban antes de
 * 2026_10_08_100200 (sin documento) y comprueba que la migración reconstruye el mismo saldo, estado y aplicaciones.
 */
class BackfillDocumentoTerceroFacturasMigracionTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesCreditFixtures;
    use CreatesFacturacionFixtures;
    use RefreshDatabase;

    private const BASE = '/api/v1/facturacion';

    private Migration $backfill;

    protected function setUp(): void
    {
        parent::setUp();

        $this->backfill = require database_path('migrations/2026_10_10_100000_backfill_documento_tercero_en_facturas_emitidas.php');
    }

    private function factura(string $valor): array
    {
        $borrador = $this->postJson(self::BASE.'/documentos', [
            'credito_id' => $this->crearCredito($this->crearCliente())->id,
            'lineas' => [['concepto' => 'INTERES.AMORT.ADM', 'descripcion' => 'Interés administrativo', 'valor' => $valor]],
        ])->assertStatus(201)->json('documento');

        return $this->postJson(self::BASE.'/documentos/'.$borrador['id'].'/emitir')->assertOk()->json('documento');
    }

    private function cobrar(array $factura, string $valor): array
    {
        return $this->postJson(self::BASE.'/documentos/'.$factura['id'].'/cobros', [
            'fecha' => '2026-10-05', 'valor' => $valor, 'medio' => 'transferencia', 'referencia' => 'REF-1',
        ])->assertStatus(201)->json('cobro');
    }

    private function nota(array $documento, string $tipo, array $linea): array
    {
        return $this->postJson(self::BASE.'/documentos/'.$documento['id'].'/notas', [
            'tipo' => $tipo, 'motivo' => 'Ajuste', 'lineas' => [$linea],
        ])->assertStatus(201)->json('documento');
    }

    /** @return array{documento: array<string, mixed>, aplicaciones: list<array<string, mixed>>} */
    private function estadoDelDocumento(int $facturaId): array
    {
        $documentoId = DB::table('documentos_facturacion')->where('id', $facturaId)->value('documento_tercero_id');
        $this->assertNotNull($documentoId);

        $documento = (array) DB::table('documentos_tercero')->where('id', $documentoId)
            ->first(['empresa_id', 'tercero_id', 'naturaleza', 'plan_cuenta_id', 'tipo_documento', 'numero_documento', 'concepto', 'valor_original', 'saldo', 'estado', 'modulo_origen', 'origen_id']);

        $aplicaciones = DB::table('aplicaciones_documento')->where('documento_tercero_id', $documentoId)->orderBy('modulo_aplicacion')->orderBy('aplicacion_id')
            ->get(['modulo_aplicacion', 'aplicacion_id', 'valor_aplicado', 'fecha'])
            ->map(fn ($fila) => (array) $fila)->all();

        return ['documento' => $documento, 'aplicaciones' => $aplicaciones];
    }

    /** Deja las facturas como quedaron en producción antes de la migración que las enlaza con documentos_tercero. */
    private function quitarDocumentosTercero(): void
    {
        DB::table('documentos_facturacion')->update(['documento_tercero_id' => null]);
        DB::table('aplicaciones_documento')->delete();
        DB::table('documentos_tercero')->delete();
    }

    public function test_reconstruye_el_mismo_documento_que_el_flujo_actual(): void
    {
        $this->actingAsAdmin();
        $this->crearReglasFacturacion();

        $sinMovimientos = $this->factura('1000');

        $conCobros = $this->factura('1000');
        $this->cobrar($conCobros, '300');
        $reversado = $this->cobrar($conCobros, '200');
        $this->postJson(self::BASE.'/cobros/'.$reversado['id'].'/reversar', ['motivo' => 'Cheque devuelto'])->assertOk();

        $conNotas = $this->factura('1000');
        $notaCredito = $this->nota($conNotas, 'nota_credito', ['linea_origen_id' => $conNotas['lineas'][0]['id'], 'valor' => '400']);
        $this->nota($conNotas, 'nota_debito', ['concepto' => 'MORA', 'descripcion' => 'Gastos de cobranza', 'valor' => '50']);
        $this->nota($notaCredito, 'nota_debito', ['concepto' => 'MORA', 'descripcion' => 'Corrige la nota', 'valor' => '20']);

        $pagada = $this->factura('500');
        $this->cobrar($pagada, '500');

        $facturas = [$sinMovimientos, $conCobros, $conNotas, $pagada];
        $esperado = array_map(fn ($factura) => $this->estadoDelDocumento($factura['id']), $facturas);

        $this->quitarDocumentosTercero();
        $this->backfill->up();

        foreach ($facturas as $i => $factura) {
            $this->assertEquals($esperado[$i], $this->estadoDelDocumento($factura['id']), "Factura {$factura['numero']}");
        }

        $this->assertSame(['pendiente', 'abonado_parcial', 'abonado_parcial', 'cancelado'], array_map(fn ($e) => $e['documento']['estado'], $esperado));
    }

    public function test_no_toca_borradores_ni_facturas_que_ya_tienen_documento_y_se_puede_repetir(): void
    {
        $this->actingAsAdmin();
        $this->crearReglasFacturacion();

        $legada = $this->factura('1000');
        $this->quitarDocumentosTercero();
        $actual = $this->factura('700');
        $borrador = $this->postJson(self::BASE.'/documentos', [
            'credito_id' => $this->crearCredito($this->crearCliente())->id,
            'lineas' => [['concepto' => 'INTERES.AMORT.ADM', 'descripcion' => 'Borrador', 'valor' => '100']],
        ])->assertStatus(201)->json('documento');

        $documentoActual = DB::table('documentos_facturacion')->where('id', $actual['id'])->value('documento_tercero_id');

        $this->backfill->up();
        $this->backfill->up();

        $this->assertSame(2, DB::table('documentos_tercero')->count());
        $this->assertSame($documentoActual, DB::table('documentos_facturacion')->where('id', $actual['id'])->value('documento_tercero_id'));
        $this->assertNotNull(DB::table('documentos_facturacion')->where('id', $legada['id'])->value('documento_tercero_id'));
        $this->assertNull(DB::table('documentos_facturacion')->where('id', $borrador['id'])->value('documento_tercero_id'));
    }
}
