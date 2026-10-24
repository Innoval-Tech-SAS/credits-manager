<?php

namespace Tests\Feature\Clientes;

use App\Clientes\Models\Cliente;
use App\Core\Dinero\Dinero;
use App\Creditos\Enums\EstadoCuota;
use App\Creditos\Models\Credito;
use App\Creditos\Models\Cuota;
use App\Pagos\Enums\FormaPago;
use App\Pagos\Models\Pago;
use App\Pagos\Models\PagoDetalle;
use App\Pagos\Models\ReversaPago;
use App\Usuarios\Models\Usuario;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesAuditoriaFixtures;
use Tests\Concerns\CreatesCreditFixtures;
use Tests\TestCase;

/**
 * Escenario (hoy = 15 jun 2026, ventana jul 2025 – jun 2026): un crédito de 900 desembolsado el 1 de enero con tres
 * cuotas de 300 a vencer el 1 de feb, 1 de mar y 1 de abr. Pagos: 300 el 1 de feb (a tiempo), 300 el 20 de mar (con
 * mora), 100 el 10 de abr y 50 el 5 de may (la tercera cuota queda abierta con 150).
 */
class ComportamientoMensualTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesAuditoriaFixtures;
    use CreatesCreditFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(CarbonImmutable::parse('2026-06-15 12:00:00', 'America/Bogota'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_serie_de_doce_meses_con_recaudo_saldo_y_cuotas_contrastado_con_la_base(): void
    {
        $this->actingAsAdmin();
        $cliente = $this->crearCliente();
        $this->escenarioBase($cliente);

        $respuesta = $this->getJson("/api/v1/clientes/{$cliente->id}/resumen");

        $respuesta->assertStatus(200);
        $meses = collect($respuesta->json('comportamiento_mensual.meses'))->keyBy('mes');

        $this->assertCount(12, $meses);
        $this->assertSame(['2025-07', '2026-06'], [$meses->keys()->first(), $meses->keys()->last()]);

        $this->assertSame(['recaudo' => '0.00', 'saldo_cierre' => '0.00', 'a_tiempo' => 0, 'mora' => 0], $this->fila($meses['2025-12']));
        $this->assertSame(['recaudo' => '0.00', 'saldo_cierre' => '900.00', 'a_tiempo' => 0, 'mora' => 0], $this->fila($meses['2026-01']));
        $this->assertSame(['recaudo' => '300.00', 'saldo_cierre' => '600.00', 'a_tiempo' => 1, 'mora' => 0], $this->fila($meses['2026-02']));
        $this->assertSame(['recaudo' => '300.00', 'saldo_cierre' => '300.00', 'a_tiempo' => 0, 'mora' => 1], $this->fila($meses['2026-03']));
        $this->assertSame(['recaudo' => '100.00', 'saldo_cierre' => '200.00', 'a_tiempo' => 0, 'mora' => 0], $this->fila($meses['2026-04']));
        $this->assertSame(['recaudo' => '50.00', 'saldo_cierre' => '150.00', 'a_tiempo' => 0, 'mora' => 0], $this->fila($meses['2026-05']));
        $this->assertSame(['recaudo' => '0.00', 'saldo_cierre' => '150.00', 'a_tiempo' => 0, 'mora' => 0], $this->fila($meses['2026-06']));

        $recaudoBase = Dinero::desdeCentavos((int) DB::table('pagos')->where('cliente_id', $cliente->id)->sum('valor_pago'))->aDecimalString();
        $this->assertSame($recaudoBase, $respuesta->json('comportamiento_mensual.totales.recaudo'));
        $this->assertSame(1, $respuesta->json('comportamiento_mensual.totales.cuotas_a_tiempo'));
        $this->assertSame(1, $respuesta->json('comportamiento_mensual.totales.cuotas_con_mora'));

        $this->assertSame($respuesta->json('saldo_consolidado'), $meses['2026-06']['saldo_cierre']);
    }

    public function test_reversa_no_cambia_el_mes_en_que_se_pago_y_devuelve_el_saldo_desde_su_mes(): void
    {
        $this->actingAsAdmin();
        $cliente = $this->crearCliente();
        [, $cuotas] = $this->escenarioBase($cliente);

        $pagoMarzo = Pago::query()->whereDate('fecha_pago', '2026-03-20')->firstOrFail();
        ReversaPago::create(['pago_id' => $pagoMarzo->id, 'fecha' => '2026-06-10', 'valor' => 300, 'motivo' => 'Consignación duplicada']);
        $cuotas[1]->update(['valor_pagado' => 0, 'estado' => EstadoCuota::Vencida]);

        $respuesta = $this->getJson("/api/v1/clientes/{$cliente->id}/resumen");

        $meses = collect($respuesta->json('comportamiento_mensual.meses'))->keyBy('mes');
        $this->assertSame('300.00', $meses['2026-03']['recaudo']);
        $this->assertSame('300.00', $meses['2026-03']['saldo_cierre']);
        $this->assertSame('150.00', $meses['2026-05']['saldo_cierre']);
        $this->assertSame('0.00', $meses['2026-06']['recaudo']);
        $this->assertSame('450.00', $meses['2026-06']['saldo_cierre']);
        $this->assertSame($respuesta->json('saldo_consolidado'), $meses['2026-06']['saldo_cierre']);
    }

    public function test_sin_autenticar_no_puede_ver_el_comportamiento(): void
    {
        $this->actingAsAdmin();
        $cliente = $this->crearCliente();
        $this->salirDeSesion();

        $this->getJson("/api/v1/clientes/{$cliente->id}/resumen")->assertStatus(401);
    }

    public function test_sin_permiso_de_ver_clientes_responde_403(): void
    {
        $this->actingAsAsesor();
        $cliente = $this->crearCliente();

        $this->getJson("/api/v1/clientes/{$cliente->id}/resumen")->assertStatus(403);
    }

    public function test_cliente_de_otra_empresa_responde_404_y_no_revela_su_comportamiento(): void
    {
        $this->actingAsAdmin();
        $cliente = $this->crearCliente();
        $this->escenarioBase($cliente);

        $this->actingAsAdmin();

        $this->getJson("/api/v1/clientes/{$cliente->id}/resumen")->assertStatus(404);
    }

    public function test_asesor_solo_ve_el_comportamiento_de_sus_clientes(): void
    {
        $asesor = $this->actingAsRolConPermisos('asesor', ['clientes.ver']);
        $propio = $this->crearCliente();
        $this->escenarioBase($propio);

        $otro = Usuario::create([
            'empresa_id' => $asesor->empresa_id, 'nombre' => 'Otro asesor', 'usuario' => 'otro_asesor_'.uniqid(),
            'password_hash' => 'irrelevante-en-tests', 'rol_id' => $asesor->rol_id, 'activo' => true,
        ]);
        $ajeno = $this->crearCliente(['asesor_id' => $otro->id, 'numero_documento' => '9990001']);
        $this->escenarioBase($ajeno);

        $this->getJson("/api/v1/clientes/{$propio->id}/resumen")
            ->assertStatus(200)
            ->assertJsonCount(12, 'comportamiento_mensual.meses');

        $this->getJson("/api/v1/clientes/{$ajeno->id}/resumen")->assertStatus(404);
    }

    /** Crea el crédito, sus cuotas y los pagos del escenario; devuelve el crédito y las cuotas en orden. */
    private function escenarioBase(Cliente $cliente): array
    {
        $credito = $this->crearCredito($cliente, [
            'valor_prestamo' => 900,
            'valor_total' => 900,
            'fecha_desembolso' => '2026-01-01',
            'desembolsado_en' => '2026-01-01 09:00:00',
        ]);

        $cuotas = [
            $this->crearCuota($credito, ['numero_cuota' => 1, 'fecha_vencimiento' => '2026-02-01', 'valor' => 300, 'valor_pagado' => 300, 'estado' => EstadoCuota::Pagada]),
            $this->crearCuota($credito, ['numero_cuota' => 2, 'fecha_vencimiento' => '2026-03-01', 'valor' => 300, 'valor_pagado' => 300, 'estado' => EstadoCuota::Pagada]),
            $this->crearCuota($credito, ['numero_cuota' => 3, 'fecha_vencimiento' => '2026-04-01', 'valor' => 300, 'valor_pagado' => 150, 'estado' => EstadoCuota::Pendiente]),
        ];

        $this->registrarPago($credito, $cliente, '2026-02-01 10:00:00', [[$cuotas[0], 300]]);
        $this->registrarPago($credito, $cliente, '2026-03-20 10:00:00', [[$cuotas[1], 300]]);
        $this->registrarPago($credito, $cliente, '2026-04-10 10:00:00', [[$cuotas[2], 100]]);
        $this->registrarPago($credito, $cliente, '2026-05-05 10:00:00', [[$cuotas[2], 50]]);

        return [$credito, $cuotas];
    }

    /** @param  array<int, array{0: Cuota, 1: int}>  $aplicaciones */
    private function registrarPago(Credito $credito, Cliente $cliente, string $fecha, array $aplicaciones): void
    {
        $pago = Pago::create([
            'credito_id' => $credito->id,
            'cliente_id' => $cliente->id,
            'valor_pago' => array_sum(array_column($aplicaciones, 1)),
            'fecha_pago' => $fecha,
            'forma_pago' => FormaPago::Efectivo,
        ]);

        foreach ($aplicaciones as [$cuota, $valor]) {
            PagoDetalle::create(['pago_id' => $pago->id, 'cuota_id' => $cuota->id, 'valor_aplicado' => $valor]);
        }
    }

    /** @return array{recaudo: string, saldo_cierre: string, a_tiempo: int, mora: int} */
    private function fila(array $mes): array
    {
        return [
            'recaudo' => $mes['recaudo'],
            'saldo_cierre' => $mes['saldo_cierre'],
            'a_tiempo' => $mes['cuotas_a_tiempo'],
            'mora' => $mes['cuotas_con_mora'],
        ];
    }
}
