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

        // En mora hace 70 días (rango 61+) y saldo 80.
        $creditoMoraVieja = $this->crearCredito($cliente, ['valor_prestamo' => 80]);
        $this->crearCuota($creditoMoraVieja, [
            'numero_cuota' => 1, 'valor' => 80, 'estado' => 'vencida', 'fecha_vencimiento' => now()->subDays(70),
        ]);

        $respuesta = $this->getJson('/api/v1/cartera');

        $respuesta->assertStatus(200);
        $respuesta->assertJsonPath('totales.al_dia.count', 2);
        $respuesta->assertJsonPath('totales.al_dia.saldo', '300.00');
        $respuesta->assertJsonPath('totales.al_dia.promedio', '150.00');

        $respuesta->assertJsonPath('totales.en_mora.count', 2);
        $respuesta->assertJsonPath('totales.en_mora.saldo', '130.00');
        $respuesta->assertJsonPath('totales.en_mora.promedio', '65.00');

        $respuesta->assertJsonPath('envejecimiento_mora.1_30.count', 1);
        $respuesta->assertJsonPath('envejecimiento_mora.1_30.saldo', '50.00');
        $respuesta->assertJsonPath('envejecimiento_mora.31_60.count', 0);
        $respuesta->assertJsonPath('envejecimiento_mora.61_mas.count', 1);
        $respuesta->assertJsonPath('envejecimiento_mora.61_mas.saldo', '80.00');
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
