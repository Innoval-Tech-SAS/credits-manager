<?php

namespace Tests\Feature\Contabilidad;

use App\Contabilidad\Enums\EstadoDocumentoTercero;
use App\Contabilidad\Enums\NaturalezaDocumento;
use App\Contabilidad\Services\DocumentoTerceroService;
use App\Core\Dinero\Dinero;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesContabilidadFixtures;
use Tests\TestCase;

class DocumentoTerceroServiceTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesContabilidadFixtures;
    use RefreshDatabase;

    public function test_crea_un_documento_pendiente_con_saldo_igual_al_valor(): void
    {
        $this->actingAsAdmin();
        $tercero = $this->crearTercero();

        $documento = app(DocumentoTerceroService::class)->crearDocumento(
            $tercero,
            NaturalezaDocumento::PorPagar,
            'factura_compra',
            'FC-001',
            '2026-01-15',
            'Compra de mercancía',
            Dinero::desdePesos(1000),
            'facturas',
            1,
        );

        $this->assertEquals(1000, $documento->saldo->aFloat());
        $this->assertSame(EstadoDocumentoTercero::Pendiente, $documento->estado);
        $this->assertDatabaseHas('documentos_tercero', ['id' => $documento->id, 'tercero_id' => $tercero->id]);
    }

    public function test_aplicar_valor_total_cancela_el_documento(): void
    {
        $this->actingAsAdmin();
        $tercero = $this->crearTercero();
        $servicio = app(DocumentoTerceroService::class);
        $documento = $servicio->crearDocumento($tercero, NaturalezaDocumento::PorPagar, 'factura_compra', 'FC-001', '2026-01-15', 'Compra', Dinero::desdePesos(1000), 'facturas', 1);

        $resultado = $servicio->aplicarValor(
            $tercero,
            NaturalezaDocumento::PorPagar,
            [['documento_tercero_id' => $documento->id, 'valor' => 1000]],
            'egresos',
            1,
        );

        $this->assertEquals(1000, $resultado['total_aplicado']->aFloat());
        $this->assertEquals(0, $documento->fresh()->saldo->aFloat());
        $this->assertSame(EstadoDocumentoTercero::Cancelado, $documento->fresh()->estado);
        $this->assertDatabaseHas('aplicaciones_documento', ['documento_tercero_id' => $documento->id, 'valor_aplicado' => 100000]);
    }

    public function test_aplicar_valor_parcial_deja_el_documento_pendiente_con_el_nuevo_saldo(): void
    {
        $this->actingAsAdmin();
        $tercero = $this->crearTercero();
        $servicio = app(DocumentoTerceroService::class);
        $documento = $servicio->crearDocumento($tercero, NaturalezaDocumento::PorPagar, 'factura_compra', 'FC-001', '2026-01-15', 'Compra', Dinero::desdePesos(1000), 'facturas', 1);

        $servicio->aplicarValor(
            $tercero,
            NaturalezaDocumento::PorPagar,
            [['documento_tercero_id' => $documento->id, 'valor' => 400]],
            'egresos',
            1,
        );

        $this->assertEquals(600, $documento->fresh()->saldo->aFloat());
        $this->assertSame(EstadoDocumentoTercero::Pendiente, $documento->fresh()->estado);
    }

    public function test_aplica_a_varios_documentos_del_mismo_tercero_en_una_sola_llamada(): void
    {
        $this->actingAsAdmin();
        $tercero = $this->crearTercero();
        $servicio = app(DocumentoTerceroService::class);
        $primero = $servicio->crearDocumento($tercero, NaturalezaDocumento::PorPagar, 'factura_compra', 'FC-001', '2026-01-15', 'Compra 1', Dinero::desdePesos(500), 'facturas', 1);
        $segundo = $servicio->crearDocumento($tercero, NaturalezaDocumento::PorPagar, 'factura_compra', 'FC-002', '2026-01-16', 'Compra 2', Dinero::desdePesos(300), 'facturas', 2);

        $resultado = $servicio->aplicarValor(
            $tercero,
            NaturalezaDocumento::PorPagar,
            [
                ['documento_tercero_id' => $primero->id, 'valor' => 500],
                ['documento_tercero_id' => $segundo->id, 'valor' => 300],
            ],
            'egresos',
            1,
        );

        $this->assertEquals(800, $resultado['total_aplicado']->aFloat());
        $this->assertSame(EstadoDocumentoTercero::Cancelado, $primero->fresh()->estado);
        $this->assertSame(EstadoDocumentoTercero::Cancelado, $segundo->fresh()->estado);
        $this->assertDatabaseCount('aplicaciones_documento', 2);
    }

    public function test_rechaza_aplicar_un_valor_mayor_al_saldo(): void
    {
        $this->actingAsAdmin();
        $tercero = $this->crearTercero();
        $servicio = app(DocumentoTerceroService::class);
        $documento = $servicio->crearDocumento($tercero, NaturalezaDocumento::PorPagar, 'factura_compra', 'FC-001', '2026-01-15', 'Compra', Dinero::desdePesos(1000), 'facturas', 1);

        $this->expectException(ValidationException::class);

        $servicio->aplicarValor(
            $tercero,
            NaturalezaDocumento::PorPagar,
            [['documento_tercero_id' => $documento->id, 'valor' => 1000.01]],
            'egresos',
            1,
        );
    }

    public function test_rechaza_aplicar_a_un_documento_que_no_pertenece_al_tercero(): void
    {
        $this->actingAsAdmin();
        $tercero = $this->crearTercero();
        $otroTercero = $this->crearTercero();
        $servicio = app(DocumentoTerceroService::class);
        $documento = $servicio->crearDocumento($otroTercero, NaturalezaDocumento::PorPagar, 'factura_compra', 'FC-001', '2026-01-15', 'Compra', Dinero::desdePesos(1000), 'facturas', 1);

        $this->expectException(ValidationException::class);

        $servicio->aplicarValor(
            $tercero,
            NaturalezaDocumento::PorPagar,
            [['documento_tercero_id' => $documento->id, 'valor' => 100]],
            'egresos',
            1,
        );
    }

    public function test_rechaza_aplicar_a_un_documento_ya_cancelado(): void
    {
        $this->actingAsAdmin();
        $tercero = $this->crearTercero();
        $servicio = app(DocumentoTerceroService::class);
        $documento = $servicio->crearDocumento($tercero, NaturalezaDocumento::PorPagar, 'factura_compra', 'FC-001', '2026-01-15', 'Compra', Dinero::desdePesos(1000), 'facturas', 1);
        $servicio->aplicarValor($tercero, NaturalezaDocumento::PorPagar, [['documento_tercero_id' => $documento->id, 'valor' => 1000]], 'egresos', 1);

        $this->expectException(ValidationException::class);

        $servicio->aplicarValor($tercero, NaturalezaDocumento::PorPagar, [['documento_tercero_id' => $documento->id, 'valor' => 1]], 'egresos', 2);
    }
}
