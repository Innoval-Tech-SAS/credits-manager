<?php

namespace Tests\Feature\Contabilidad;

use App\Contabilidad\Enums\EstadoComprobante;
use App\Contabilidad\Enums\EstadoPeriodo;
use App\Contabilidad\Models\PeriodoContable;
use App\Contabilidad\Services\ComprobanteService;
use App\Core\Dinero\Dinero;
use App\Pagos\Models\Pago;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesContabilidadFixtures;
use Tests\TestCase;

class ComprobanteServiceTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesContabilidadFixtures;
    use RefreshDatabase;

    public function test_crea_y_contabiliza_un_comprobante_balanceado(): void
    {
        $this->actingAsAdmin();
        $tipo = $this->crearTipoComprobante();
        $debito = $this->crearCuenta();
        $credito = $this->crearCuenta();

        $comprobante = app(ComprobanteService::class)->crearComprobante(
            $tipo->id,
            '2026-01-15',
            [
                ['cuenta_id' => $debito->id, 'debito' => 100, 'credito' => 0],
                ['cuenta_id' => $credito->id, 'debito' => 0, 'credito' => 100],
            ],
            'Comprobante de prueba',
        );

        $this->assertSame(EstadoComprobante::Contabilizado, $comprobante->estado);
        $this->assertEquals(1, $comprobante->consecutivo);
        $this->assertDatabaseCount('asientos_contables', 2);
        $this->assertDatabaseHas('periodos_contables', ['anio' => 2026, 'mes' => 1, 'estado' => EstadoPeriodo::Abierto->value]);
    }

    public function test_rechaza_un_comprobante_descuadrado(): void
    {
        $this->actingAsAdmin();
        $tipo = $this->crearTipoComprobante();
        $cuenta = $this->crearCuenta();

        $this->expectException(ValidationException::class);

        app(ComprobanteService::class)->crearComprobante(
            $tipo->id,
            '2026-01-15',
            [
                ['cuenta_id' => $cuenta->id, 'debito' => 100, 'credito' => 0],
                ['cuenta_id' => $cuenta->id, 'debito' => 0, 'credito' => 50],
            ],
            'Descuadrado',
        );
    }

    public function test_no_permite_contabilizar_en_un_periodo_cerrado(): void
    {
        $this->actingAsAdmin();
        $tipo = $this->crearTipoComprobante();
        $debito = $this->crearCuenta();
        $credito = $this->crearCuenta();

        PeriodoContable::create(['anio' => 2026, 'mes' => 1, 'estado' => EstadoPeriodo::Cerrado]);

        $this->expectException(ValidationException::class);

        app(ComprobanteService::class)->crearComprobante(
            $tipo->id,
            '2026-01-15',
            [
                ['cuenta_id' => $debito->id, 'debito' => 100, 'credito' => 0],
                ['cuenta_id' => $credito->id, 'debito' => 0, 'credito' => 100],
            ],
            'Bloqueado',
        );
    }

    public function test_reversar_genera_un_comprobante_inverso_y_anula_el_original(): void
    {
        $this->actingAsAdmin();
        $servicio = app(ComprobanteService::class);
        $tipo = $this->crearTipoComprobante();
        $debito = $this->crearCuenta();
        $credito = $this->crearCuenta();

        $original = $servicio->crearComprobante(
            $tipo->id,
            '2026-01-15',
            [
                ['cuenta_id' => $debito->id, 'debito' => 100, 'credito' => 0],
                ['cuenta_id' => $credito->id, 'debito' => 0, 'credito' => 100],
            ],
            'Original',
        );

        $reverso = $servicio->reversarComprobante($original, 'Error de digitación');

        $this->assertSame(EstadoComprobante::Anulado, $original->fresh()->estado);
        $this->assertEquals($reverso->id, $original->fresh()->comprobante_reverso_id);
        $this->assertSame(EstadoComprobante::Contabilizado, $reverso->estado);
        $this->assertDatabaseHas('asientos_contables', [
            'comprobante_id' => $reverso->id,
            'cuenta_id' => $debito->id,
            'debito' => 0,
            'credito' => 10000,
        ]);
        // El original nunca se elimina.
        $this->assertDatabaseHas('comprobantes', ['id' => $original->id]);
    }

    public function test_no_permite_reversar_un_comprobante_ya_anulado(): void
    {
        $this->actingAsAdmin();
        $servicio = app(ComprobanteService::class);
        $tipo = $this->crearTipoComprobante();
        $debito = $this->crearCuenta();
        $credito = $this->crearCuenta();

        $original = $servicio->crearComprobante(
            $tipo->id,
            '2026-01-15',
            [
                ['cuenta_id' => $debito->id, 'debito' => 100, 'credito' => 0],
                ['cuenta_id' => $credito->id, 'debito' => 0, 'credito' => 100],
            ],
            'Original',
        );
        $servicio->reversarComprobante($original, 'Primer reverso');

        $this->expectException(ValidationException::class);

        $servicio->reversarComprobante($original->fresh(), 'Segundo intento');
    }

    public function test_el_consecutivo_se_incrementa_por_tipo_de_comprobante(): void
    {
        $this->actingAsAdmin();
        $servicio = app(ComprobanteService::class);
        $tipo = $this->crearTipoComprobante();
        $debito = $this->crearCuenta();
        $credito = $this->crearCuenta();

        $lineas = [
            ['cuenta_id' => $debito->id, 'debito' => 10, 'credito' => 0],
            ['cuenta_id' => $credito->id, 'debito' => 0, 'credito' => 10],
        ];

        $primero = $servicio->crearComprobante($tipo->id, '2026-01-10', $lineas, 'Uno');
        $segundo = $servicio->crearComprobante($tipo->id, '2026-01-11', $lineas, 'Dos');

        $this->assertEquals(1, $primero->consecutivo);
        $this->assertEquals(2, $segundo->consecutivo);
    }

    public function test_contabilizar_recaudo_no_bloquea_si_no_hay_regla_configurada(): void
    {
        $this->actingAsAdmin();

        // Ni siquiera necesita un Pago persistido: sin reglas activas, el motor
        // retorna null antes de tocar cualquier propiedad del pago.
        $resultado = app(ComprobanteService::class)->contabilizarRecaudoPago(new Pago, Dinero::desdePesos(100), Dinero::cero());

        $this->assertNull($resultado);
    }
}
