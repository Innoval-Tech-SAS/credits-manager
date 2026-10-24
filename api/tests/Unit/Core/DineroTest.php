<?php

namespace Tests\Unit\Core;

use App\Core\Dinero\Dinero;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class DineroTest extends TestCase
{
    public function test_desde_pesos_acepta_int_float_y_string_decimal(): void
    {
        $this->assertSame(100000, Dinero::desdePesos(1000)->centavos());
        $this->assertSame(100050, Dinero::desdePesos(1000.5)->centavos());
        $this->assertSame(100050, Dinero::desdePesos('1000.50')->centavos());
        $this->assertSame(-100050, Dinero::desdePesos('-1000.50')->centavos());
        $this->assertSame(0, Dinero::desdePesos('0')->centavos());
    }

    public function test_desde_pesos_string_invalida_lanza_excepcion(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Dinero::desdePesos('12.345');
    }

    public function test_suma_y_resta_son_exactas_sin_deriva_de_flotante(): void
    {
        $suma = Dinero::sumarVarios(
            Dinero::desdePesos('0.1'),
            Dinero::desdePesos('0.2'),
        );

        $this->assertSame('0.30', $suma->aDecimalString());
        $this->assertSame(30, $suma->centavos());
    }

    public function test_resta_produce_saldo_correcto(): void
    {
        $saldo = Dinero::desdePesos(1000)->restar(Dinero::desdePesos('302.11'));

        $this->assertSame('697.89', $saldo->aDecimalString());
    }

    public function test_multiplicar_por_tasa_redondea_una_sola_vez(): void
    {
        $interes = Dinero::desdePesos(1000)->multiplicarPorTasa(0.1);

        $this->assertSame('100.00', $interes->aDecimalString());
    }

    public function test_dividir_entre_trunca_hacia_abajo(): void
    {
        $cuota = Dinero::desdePesos(1000)->dividirEntre(3);

        $this->assertSame('333.33', $cuota->aDecimalString());
    }

    public function test_no_negativo_convierte_negativos_a_cero(): void
    {
        $negativo = Dinero::desdePesos(-50);

        $this->assertTrue($negativo->esNegativo());
        $this->assertTrue($negativo->noNegativo()->esCero());
    }

    public function test_comparaciones(): void
    {
        $diez = Dinero::desdePesos(10);
        $veinte = Dinero::desdePesos(20);

        $this->assertTrue($diez->menorQue($veinte));
        $this->assertTrue($veinte->mayorQue($diez));
        $this->assertTrue($diez->igualA(Dinero::desdePesos(10)));
        $this->assertSame($diez, $diez->menorEntre($veinte));
        $this->assertSame($veinte, $diez->mayorEntre($veinte));
    }

    public function test_a_decimal_string_formatea_con_dos_cifras_y_signo(): void
    {
        $this->assertSame('12345.66', Dinero::desdePesos('12345.66')->aDecimalString());
        $this->assertSame('0.05', Dinero::desdeCentavos(5)->aDecimalString());
        $this->assertSame('-0.05', Dinero::desdeCentavos(-5)->aDecimalString());
        $this->assertSame('0.00', Dinero::cero()->aDecimalString());
    }

    public function test_json_serialize_devuelve_el_string_decimal(): void
    {
        $this->assertSame('"12345.66"', json_encode(Dinero::desdePesos('12345.66')));
    }
}
