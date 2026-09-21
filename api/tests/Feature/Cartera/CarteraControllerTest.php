<?php

namespace Tests\Feature\Cartera;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesCreditFixtures;
use Tests\TestCase;

class CarteraControllerTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesCreditFixtures;
    use RefreshDatabase;

    public function test_calcula_el_promedio_por_credito_y_el_envejecimiento_de_la_mora(): void
    {
        $this->actingAsAdmin();
        $cliente = $this->crearCliente();

        // Al día: dos créditos de 100 y 200 -> promedio 150.
        $creditoAlDia1 = $this->crearCredito($cliente, ['valor_prestamo' => 100]);
        $this->crearCuota($creditoAlDia1, ['numero_cuota' => 1, 'valor' => 100, 'fecha_vencimiento' => now()->addDays(10)]);

        $creditoAlDia2 = $this->crearCredito($cliente, ['valor_prestamo' => 200]);
        $this->crearCuota($creditoAlDia2, ['numero_cuota' => 1, 'valor' => 200, 'fecha_vencimiento' => now()->addDays(10)]);

        // En mora hace 10 días (rango 1-30) y saldo 50.
        $creditoMoraReciente = $this->crearCredito($cliente, ['valor_prestamo' => 50]);
        $this->crearCuota($creditoMoraReciente, [
            'numero_cuota' => 1, 'valor' => 50, 'estado' => 'vencida', 'fecha_vencimiento' => now()->subDays(10),
        ]);

        // En mora hace 70 días (rango 61-90) y saldo 80.
        $creditoMoraVieja = $this->crearCredito($cliente, ['valor_prestamo' => 80]);
        $this->crearCuota($creditoMoraVieja, [
            'numero_cuota' => 1, 'valor' => 80, 'estado' => 'vencida', 'fecha_vencimiento' => now()->subDays(70),
        ]);

        // En mora hace 120 días (rango de más de 90) y saldo 30.
        $creditoMoraCronica = $this->crearCredito($cliente, ['valor_prestamo' => 30]);
        $this->crearCuota($creditoMoraCronica, [
            'numero_cuota' => 1, 'valor' => 30, 'estado' => 'vencida', 'fecha_vencimiento' => now()->subDays(120),
        ]);

        $respuesta = $this->getJson('/api/v1/cartera');

        $respuesta->assertStatus(200);
        $respuesta->assertJsonPath('totales.al_dia.count', 2);
        $respuesta->assertJsonPath('totales.al_dia.saldo', '300.00');
        $respuesta->assertJsonPath('totales.al_dia.promedio', '150.00');

        $respuesta->assertJsonPath('totales.en_mora.count', 3);
        $respuesta->assertJsonPath('totales.en_mora.saldo', '160.00');
        $respuesta->assertJsonPath('totales.en_mora.promedio', '53.33');

        $respuesta->assertJsonPath('envejecimiento_mora.1_30.count', 1);
        $respuesta->assertJsonPath('envejecimiento_mora.1_30.saldo', '50.00');
        $respuesta->assertJsonPath('envejecimiento_mora.31_60.count', 0);
        $respuesta->assertJsonPath('envejecimiento_mora.31_60.saldo', '0.00');
        $respuesta->assertJsonPath('envejecimiento_mora.61_90.count', 1);
        $respuesta->assertJsonPath('envejecimiento_mora.61_90.saldo', '80.00');
        $respuesta->assertJsonPath('envejecimiento_mora.mas_90.count', 1);
        $respuesta->assertJsonPath('envejecimiento_mora.mas_90.saldo', '30.00');
    }

    public function test_clasifica_la_mora_en_los_limites_exactos_de_cada_rango(): void
    {
        $this->actingAsAdmin();
        $cliente = $this->crearCliente();

        // Un crédito por cada día límite: la mora se marca desde el día 1 de atraso.
        $esperados = ['1_30' => [1, 30], '31_60' => [31, 60], '61_90' => [61, 90], 'mas_90' => [91, 400]];

        foreach ($esperados as $dias) {
            foreach ($dias as $dia) {
                $credito = $this->crearCredito($cliente, ['valor_prestamo' => 10]);
                $this->crearCuota($credito, [
                    'numero_cuota' => 1, 'valor' => 10, 'estado' => 'vencida', 'fecha_vencimiento' => now()->subDays($dia)->toDateString(),
                ]);
            }
        }

        $respuesta = $this->getJson('/api/v1/cartera')->assertOk();

        $respuesta->assertJsonPath('totales.en_mora.count', 8);
        $this->assertSame(['1_30', '31_60', '61_90', 'mas_90'], array_keys($respuesta->json('envejecimiento_mora')));

        foreach (array_keys($esperados) as $rango) {
            $respuesta->assertJsonPath("envejecimiento_mora.{$rango}.count", 2);
            $respuesta->assertJsonPath("envejecimiento_mora.{$rango}.saldo", '20.00');
        }
    }

    public function test_promedios_en_cero_cuando_no_hay_creditos(): void
    {
        $this->actingAsAdmin();

        $respuesta = $this->getJson('/api/v1/cartera');

        $respuesta->assertStatus(200);
        $respuesta->assertJsonPath('totales.al_dia.promedio', '0.00');
        $respuesta->assertJsonPath('totales.en_mora.promedio', '0.00');
    }
}
