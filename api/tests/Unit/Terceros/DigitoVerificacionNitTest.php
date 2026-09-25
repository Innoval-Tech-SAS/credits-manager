<?php

namespace Tests\Unit\Terceros;

use App\Terceros\Services\DigitoVerificacionNit;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DigitoVerificacionNitTest extends TestCase
{
    public static function nitsConocidos(): array
    {
        return [
            'DIAN' => ['800197268', 4],
            'Banco de Bogotá' => ['860002964', 4],
            'Ecopetrol' => ['899999068', 1],
            'residuo cero' => ['900123456', 8],
        ];
    }

    #[DataProvider('nitsConocidos')]
    public function test_calcula_el_digito_de_verificacion_de_nits_conocidos(string $nit, int $digito): void
    {
        $this->assertSame($digito, DigitoVerificacionNit::calcular($nit));
    }

    public function test_ignora_los_separadores_del_nit(): void
    {
        $this->assertSame(4, DigitoVerificacionNit::calcular('800.197.268'));
    }
}
