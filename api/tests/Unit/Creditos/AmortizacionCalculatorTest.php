<?php

namespace Tests\Unit\Creditos;

use App\Core\Dinero\Dinero;
use App\Creditos\Enums\TipoPago;
use App\Creditos\Services\AmortizacionCalculator;
use PHPUnit\Framework\TestCase;

class AmortizacionCalculatorTest extends TestCase
{
    /**
     * Caso de referencia calculado a mano (1000 al 10% mensual, 3 cuotas):
     * cuota fija 402.11, última cuota 402.13 (absorbe el redondeo del saldo).
     * Con `Dinero` (centavos enteros) el resultado es exacto, así que se compara
     * con `assertSame` sobre centavos en vez de `assertEqualsWithDelta` — si
     * volviera a hacer falta un delta, sería señal de que quedó un float colado.
     */
    public function test_sistema_frances_con_interes_calculado_a_mano(): void
    {
        $resultado = AmortizacionCalculator::calcular(Dinero::desdePesos(1000), 10, 3, TipoPago::Mensual, '2026-01-01');

        $this->assertSame(40211, $resultado['valor_cuota']->centavos());
        $this->assertSame(120635, $resultado['valor_total']->centavos());
        $this->assertCount(3, $resultado['cuotas']);

        [$c1, $c2, $c3] = $resultado['cuotas'];

        $this->assertCuota(['numero_cuota' => 1, 'fecha_vencimiento' => '2026-02-01', 'valor' => 40211, 'valor_interes' => 10000, 'valor_capital' => 30211, 'saldo_capital' => 69789], $c1);
        $this->assertCuota(['numero_cuota' => 2, 'fecha_vencimiento' => '2026-03-01', 'valor' => 40211, 'valor_interes' => 6979, 'valor_capital' => 33232, 'saldo_capital' => 36557], $c2);
        $this->assertCuota(['numero_cuota' => 3, 'fecha_vencimiento' => '2026-04-01', 'valor' => 40213, 'valor_interes' => 3656, 'valor_capital' => 36557, 'saldo_capital' => 0], $c3);
    }

    private function assertCuota(array $esperada, array $real): void
    {
        $this->assertSame($esperada['numero_cuota'], $real['numero_cuota']);
        $this->assertSame($esperada['fecha_vencimiento'], $real['fecha_vencimiento']);
        $this->assertSame($esperada['valor'], $real['valor']->centavos());
        $this->assertSame($esperada['valor_interes'], $real['valor_interes']->centavos());
        $this->assertSame($esperada['valor_capital'], $real['valor_capital']->centavos());
        $this->assertSame($esperada['saldo_capital'], $real['saldo_capital']->centavos());
    }

    /**
     * Sin interés, préstamo que no divide exacto entre el plazo: la última
     * cuota absorbe el centavo de redondeo (mismo comportamiento documentado
     * para el sistema francés, no exclusivo de créditos con interés).
     */
    public function test_ultima_cuota_absorbe_el_redondeo_sin_interes(): void
    {
        $resultado = AmortizacionCalculator::calcular(Dinero::desdePesos(1000), 0, 3, TipoPago::Mensual, '2026-01-01');

        $this->assertSame(33333, $resultado['valor_cuota']->centavos());
        $this->assertSame(100000, $resultado['valor_total']->centavos());

        $valores = array_map(fn (Dinero $v) => $v->centavos(), array_column($resultado['cuotas'], 'valor'));
        $this->assertSame([33333, 33333, 33334], $valores);

        $this->assertSame(0, $resultado['cuotas'][2]['saldo_capital']->centavos());
    }

    /**
     * Invariante estructural: la suma del capital de todas las cuotas debe
     * saldar exactamente el valor prestado, sin importar tasa/plazo/tipo de pago.
     * Con aritmética entera esto ya no es "verificado con delta" — es una
     * garantía matemática (resta pura de enteros en cada iteración).
     */
    public function test_la_suma_del_capital_de_las_cuotas_salda_el_prestamo(): void
    {
        $casos = [
            [1500000, 7, 6, TipoPago::Mensual],
            [850000, 2.5, 12, TipoPago::Quincenal],
            [50000, 0, 4, TipoPago::Mensual],
        ];

        foreach ($casos as [$valorPrestamo, $tasa, $plazo, $tipoPago]) {
            $prestamo = Dinero::desdePesos($valorPrestamo);
            $resultado = AmortizacionCalculator::calcular($prestamo, $tasa, $plazo, $tipoPago, '2026-01-01');

            $capitalTotal = Dinero::sumarVarios(...array_column($resultado['cuotas'], 'valor_capital'));

            $this->assertSame($prestamo->centavos(), $capitalTotal->centavos(), "Capital total no salda el préstamo para el caso [{$valorPrestamo}, {$tasa}%, {$plazo}]");
            $this->assertSame(0, end($resultado['cuotas'])['saldo_capital']->centavos());
            $this->assertCount($plazo, $resultado['cuotas']);
        }
    }

    public function test_periodicidad_quincenal_suma_15_dias_por_cuota(): void
    {
        $resultado = AmortizacionCalculator::calcular(Dinero::desdePesos(300000), 0, 3, TipoPago::Quincenal, '2026-01-01');

        $fechas = array_column($resultado['cuotas'], 'fecha_vencimiento');

        $this->assertSame(['2026-01-16', '2026-01-31', '2026-02-15'], $fechas);
    }
}
