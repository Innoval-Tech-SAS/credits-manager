<?php

namespace Tests\Unit\Impresion;

use App\Core\Dinero\Dinero;
use App\Impresion\Services\NumeroALetras;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NumeroALetrasTest extends TestCase
{
    /** @return array<string, array{0: int, 1: string}> pesos enteros => texto esperado */
    public static function montos(): array
    {
        return [
            'el de la plantilla de DDA' => [1_222_380, 'Un millón doscientos veintidós mil trescientos ochenta pesos m/cte'],
            'cero' => [0, 'Cero pesos m/cte'],
            'un peso' => [1, 'Un peso m/cte'],
            'veintiún pesos' => [21, 'Veintiún pesos m/cte'],
            'treinta y un pesos' => [31, 'Treinta y un pesos m/cte'],
            'cien exacto' => [100, 'Cien pesos m/cte'],
            'ciento un pesos' => [101, 'Ciento un pesos m/cte'],
            'quinientos' => [500, 'Quinientos pesos m/cte'],
            'mil' => [1_000, 'Mil pesos m/cte'],
            'veintiún mil' => [21_000, 'Veintiún mil pesos m/cte'],
            'treinta y un mil' => [31_000, 'Treinta y un mil pesos m/cte'],
            'cien mil' => [100_000, 'Cien mil pesos m/cte'],
            'quince mil quinientos' => [15_500, 'Quince mil quinientos pesos m/cte'],
            'un millón exacto' => [1_000_000, 'Un millón de pesos m/cte'],
            'dos millones exactos' => [2_000_000, 'Dos millones de pesos m/cte'],
            'veintiún millones' => [21_000_000, 'Veintiún millones de pesos m/cte'],
            'millones con resto' => [3_450_100, 'Tres millones cuatrocientos cincuenta mil cien pesos m/cte'],
            'mil millones' => [1_000_000_000, 'Mil millones de pesos m/cte'],
            'mil quinientos millones' => [1_500_000_000, 'Mil quinientos millones de pesos m/cte'],
            'once millones novecientos noventa y nueve mil novecientos noventa y nueve' => [
                11_999_999, 'Once millones novecientos noventa y nueve mil novecientos noventa y nueve pesos m/cte',
            ],
        ];
    }

    #[DataProvider('montos')]
    public function test_escribe_el_monto_en_letras(int $pesos, string $esperado): void
    {
        $this->assertSame($esperado, NumeroALetras::pesos(Dinero::desdePesos($pesos)));
    }

    public function test_los_centavos_se_escriben_como_fraccion(): void
    {
        $this->assertSame('Mil doscientos pesos con 50/100 m/cte', NumeroALetras::pesos(Dinero::desdeCentavos(120_050)));
        $this->assertSame('Cero pesos con 05/100 m/cte', NumeroALetras::pesos(Dinero::desdeCentavos(5)));
    }

    public function test_un_monto_negativo_lleva_menos(): void
    {
        $this->assertSame('Menos quinientos pesos m/cte', NumeroALetras::pesos(Dinero::desdePesos(-500)));
    }

    public function test_rechaza_un_monto_que_no_se_puede_escribir(): void
    {
        $this->expectException(InvalidArgumentException::class);

        NumeroALetras::pesos(Dinero::desdePesos(1_000_000_000_000));
    }
}
