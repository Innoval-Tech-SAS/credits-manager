<?php

namespace Tests\Feature\Contabilidad;

use App\Contabilidad\Services\ComprobanteService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesContabilidadFixtures;
use Tests\TestCase;

class ConsecutivoComprobanteTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesContabilidadFixtures;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_sin_reinicio_el_ciclo_de_numeracion_queda_vacio(): void
    {
        $this->actingAsAdmin();
        $tipo = $this->crearTipoComprobante();
        $debito = $this->crearCuenta();
        $credito = $this->crearCuenta();

        $comprobante = app(ComprobanteService::class)->crearComprobante(
            $tipo->id,
            '2026-01-15',
            [
                ['cuenta_id' => $debito->id, 'debito' => 10, 'credito' => 0],
                ['cuenta_id' => $credito->id, 'debito' => 0, 'credito' => 10],
            ],
            'Sin reinicio',
        );

        $this->assertSame('', $comprobante->ciclo_numeracion);
        $this->assertSame("{$tipo->codigo} 1", $comprobante->numero);
    }

    public function test_el_reinicio_anual_reinicia_el_consecutivo_al_cambiar_de_anio(): void
    {
        $this->actingAsAdmin();
        $tipo = $this->crearTipoComprobante();
        $tipo->update(['reinicio_periodo' => 'anual']);
        $debito = $this->crearCuenta();
        $credito = $this->crearCuenta();
        $servicio = app(ComprobanteService::class);
        $lineas = [
            ['cuenta_id' => $debito->id, 'debito' => 10, 'credito' => 0],
            ['cuenta_id' => $credito->id, 'debito' => 0, 'credito' => 10],
        ];

        Carbon::setTestNow('2026-12-30');
        $deEsteAnio = $servicio->crearComprobante($tipo->id, '2026-12-30', $lineas, 'Fin de año');

        Carbon::setTestNow('2027-01-02');
        $delAnioNuevo = $servicio->crearComprobante($tipo->id, '2027-01-02', $lineas, 'Año nuevo');

        $this->assertSame('2026', $deEsteAnio->ciclo_numeracion);
        $this->assertEquals(1, $deEsteAnio->consecutivo);
        $this->assertSame('2027', $delAnioNuevo->ciclo_numeracion);
        $this->assertEquals(1, $delAnioNuevo->consecutivo);
        $this->assertSame("{$tipo->codigo}-2027-1", $delAnioNuevo->numero);
    }

    public function test_el_reinicio_mensual_reinicia_el_consecutivo_al_cambiar_de_mes(): void
    {
        $this->actingAsAdmin();
        $tipo = $this->crearTipoComprobante();
        $tipo->update(['reinicio_periodo' => 'mensual']);
        $debito = $this->crearCuenta();
        $credito = $this->crearCuenta();
        $servicio = app(ComprobanteService::class);
        $lineas = [
            ['cuenta_id' => $debito->id, 'debito' => 10, 'credito' => 0],
            ['cuenta_id' => $credito->id, 'debito' => 0, 'credito' => 10],
        ];

        Carbon::setTestNow('2026-01-31');
        $servicio->crearComprobante($tipo->id, '2026-01-31', $lineas, 'Enero');

        Carbon::setTestNow('2026-02-01');
        $deFebrero = $servicio->crearComprobante($tipo->id, '2026-02-01', $lineas, 'Febrero');

        $this->assertSame('2026-02', $deFebrero->ciclo_numeracion);
        $this->assertEquals(1, $deFebrero->consecutivo);
    }

    public function test_no_se_puede_superar_el_consecutivo_final(): void
    {
        $this->actingAsAdmin();
        $tipo = $this->crearTipoComprobante(['consecutivo_final' => 1]);
        $debito = $this->crearCuenta();
        $credito = $this->crearCuenta();
        $servicio = app(ComprobanteService::class);
        $lineas = [
            ['cuenta_id' => $debito->id, 'debito' => 10, 'credito' => 0],
            ['cuenta_id' => $credito->id, 'debito' => 0, 'credito' => 10],
        ];

        $servicio->crearComprobante($tipo->id, '2026-01-15', $lineas, 'Primero');

        $this->expectException(ValidationException::class);

        $servicio->crearComprobante($tipo->id, '2026-01-16', $lineas, 'Agotado');
    }

    public function test_crea_un_comprobante_manual_con_consecutivo_digitado(): void
    {
        $this->actingAsAdmin();
        $tipo = $this->crearTipoComprobante();
        $tipo->update(['tipo_numeracion' => 'manual']);
        $debito = $this->crearCuenta();
        $credito = $this->crearCuenta();

        $respuesta = $this->postJson('/api/v1/contabilidad/comprobantes', [
            'tipo_comprobante_id' => $tipo->id,
            'consecutivo' => 500,
            'fecha' => now()->toDateString(),
            'concepto' => 'Manual',
            'lineas' => [
                ['cuenta_id' => $debito->id, 'debito' => 100, 'credito' => 0],
                ['cuenta_id' => $credito->id, 'debito' => 0, 'credito' => 100],
            ],
        ]);

        $respuesta->assertCreated()->assertJsonPath('comprobante.consecutivo', 500);
        $this->assertDatabaseHas('comprobantes', ['tipo_comprobante_id' => $tipo->id, 'consecutivo' => 500]);
    }

    public function test_numeracion_manual_exige_el_consecutivo(): void
    {
        $this->actingAsAdmin();
        $tipo = $this->crearTipoComprobante();
        $tipo->update(['tipo_numeracion' => 'manual']);
        $debito = $this->crearCuenta();
        $credito = $this->crearCuenta();

        $respuesta = $this->postJson('/api/v1/contabilidad/comprobantes', [
            'tipo_comprobante_id' => $tipo->id,
            'fecha' => now()->toDateString(),
            'concepto' => 'Sin consecutivo',
            'lineas' => [
                ['cuenta_id' => $debito->id, 'debito' => 100, 'credito' => 0],
                ['cuenta_id' => $credito->id, 'debito' => 0, 'credito' => 100],
            ],
        ]);

        $respuesta->assertStatus(422)->assertJsonValidationErrors('consecutivo');
    }

    public function test_numeracion_manual_rechaza_un_consecutivo_fuera_de_rango(): void
    {
        $this->actingAsAdmin();
        $tipo = $this->crearTipoComprobante(['consecutivo_final' => 10]);
        $tipo->update(['tipo_numeracion' => 'manual']);
        $debito = $this->crearCuenta();
        $credito = $this->crearCuenta();

        $respuesta = $this->postJson('/api/v1/contabilidad/comprobantes', [
            'tipo_comprobante_id' => $tipo->id,
            'consecutivo' => 999,
            'fecha' => now()->toDateString(),
            'concepto' => 'Fuera de rango',
            'lineas' => [
                ['cuenta_id' => $debito->id, 'debito' => 100, 'credito' => 0],
                ['cuenta_id' => $credito->id, 'debito' => 0, 'credito' => 100],
            ],
        ]);

        $respuesta->assertStatus(422)->assertJsonValidationErrors('consecutivo');
    }

    public function test_numeracion_manual_rechaza_un_consecutivo_duplicado(): void
    {
        $this->actingAsAdmin();
        $tipo = $this->crearTipoComprobante();
        $tipo->update(['tipo_numeracion' => 'manual']);
        $debito = $this->crearCuenta();
        $credito = $this->crearCuenta();
        $lineas = [
            ['cuenta_id' => $debito->id, 'debito' => 100, 'credito' => 0],
            ['cuenta_id' => $credito->id, 'debito' => 0, 'credito' => 100],
        ];

        $this->postJson('/api/v1/contabilidad/comprobantes', [
            'tipo_comprobante_id' => $tipo->id,
            'consecutivo' => 7,
            'fecha' => now()->toDateString(),
            'concepto' => 'Primero',
            'lineas' => $lineas,
        ])->assertCreated();

        $respuesta = $this->postJson('/api/v1/contabilidad/comprobantes', [
            'tipo_comprobante_id' => $tipo->id,
            'consecutivo' => 7,
            'fecha' => now()->toDateString(),
            'concepto' => 'Repetido',
            'lineas' => $lineas,
        ]);

        $respuesta->assertStatus(422)->assertJsonValidationErrors('consecutivo');
    }

    public function test_un_flujo_automatico_sobre_un_tipo_en_modo_manual_sigue_autoincrementando(): void
    {
        $this->actingAsAdmin();
        $tipo = $this->crearTipoComprobante();
        $tipo->update(['tipo_numeracion' => 'manual']);
        $debito = $this->crearCuenta();
        $credito = $this->crearCuenta();

        // Origen distinto de 'manual': el flujo automático nunca exige ni acepta un consecutivo digitado.
        $comprobante = app(ComprobanteService::class)->crearComprobante(
            $tipo->id,
            '2026-01-15',
            [
                ['cuenta_id' => $debito->id, 'debito' => 10, 'credito' => 0],
                ['cuenta_id' => $credito->id, 'debito' => 0, 'credito' => 10],
            ],
            'Desembolso automático',
            'creditos',
        );

        $this->assertEquals(1, $comprobante->consecutivo);
    }

    public function test_una_creacion_que_falla_dentro_de_la_transaccion_no_deja_un_hueco_en_la_numeracion(): void
    {
        $this->actingAsAdmin();
        $tipo = $this->crearTipoComprobante();
        $debito = $this->crearCuenta();
        $credito = $this->crearCuenta();
        $servicio = app(ComprobanteService::class);

        try {
            $servicio->crearComprobante(
                $tipo->id,
                '2026-01-15',
                [
                    ['cuenta_id' => $debito->id, 'debito' => 10, 'credito' => 0],
                    // Tercero inexistente: la FK de asientos_contables falla DENTRO de la transacción,
                    // después de ya haberse asignado el consecutivo. (Una cuenta inexistente ya se rechaza antes.)
                    ['cuenta_id' => $credito->id, 'tercero_id' => 999999, 'debito' => 0, 'credito' => 10],
                ],
                'Falla después de asignar el consecutivo',
            );
            $this->fail('Se esperaba que la creación fallara.');
        } catch (QueryException) {
            // Esperado: la FK de terceros rechaza el tercero inexistente.
        }

        $this->assertSame(0, $tipo->fresh()->consecutivo_actual);

        $comprobante = $servicio->crearComprobante(
            $tipo->id,
            '2026-01-16',
            [
                ['cuenta_id' => $debito->id, 'debito' => 10, 'credito' => 0],
                ['cuenta_id' => $credito->id, 'debito' => 0, 'credito' => 10],
            ],
            'Primero de verdad',
        );

        $this->assertEquals(1, $comprobante->consecutivo);
    }
}
