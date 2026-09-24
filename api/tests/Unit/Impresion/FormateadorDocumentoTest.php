<?php

namespace Tests\Unit\Impresion;

use App\Core\Dinero\Dinero;
use App\Impresion\Data\ColumnaDocumento;
use App\Impresion\Enums\TipoColumnaDocumento;
use App\Impresion\Services\FormateadorDocumento;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class FormateadorDocumentoTest extends TestCase
{
    private FormateadorDocumento $formateador;

    protected function setUp(): void
    {
        $this->formateador = new FormateadorDocumento;
    }

    public function test_el_dinero_lleva_coma_de_miles_y_dos_decimales(): void
    {
        $this->assertSame('1,222,380.00', $this->formateador->dinero(Dinero::desdePesos(1_222_380)));
        $this->assertSame('0.05', $this->formateador->dinero(Dinero::desdeCentavos(5)));
        $this->assertSame('1,000.50', $this->formateador->dinero(Dinero::desdeCentavos(100_050)));
        $this->assertSame('-2,500.00', $this->formateador->dinero(Dinero::desdePesos(-2500)));
    }

    public function test_las_fechas_salen_en_hora_de_bogota(): void
    {
        $instante = CarbonImmutable::parse('2026-07-16 02:19:00', 'UTC');

        $this->assertSame('15/07/2026, 21:19', $this->formateador->fecha($instante, true));
        $this->assertSame('15/07/2026', $this->formateador->fecha($instante));
    }

    public function test_formatea_cada_celda_segun_el_tipo_de_su_columna(): void
    {
        $celda = fn (mixed $valor, TipoColumnaDocumento $tipo) => $this->formateador->celda($valor, new ColumnaDocumento('c', 'C', $tipo));

        $this->assertSame('1,222,380.00', $celda(Dinero::desdePesos(1_222_380), TipoColumnaDocumento::Monetario));
        $this->assertSame('1,500.25', $celda('1500.25', TipoColumnaDocumento::Monetario));
        $this->assertSame('1.00', $celda(1, TipoColumnaDocumento::Decimal));
        $this->assertSame('12,345', $celda(12345, TipoColumnaDocumento::Entero));
        $this->assertSame('1.10%', $celda(1.1, TipoColumnaDocumento::Porcentaje));
        $this->assertSame('15/07/2026', $celda(CarbonImmutable::parse('2026-07-16 02:19:00', 'UTC'), TipoColumnaDocumento::Fecha));
        $this->assertSame('Interés', $celda('Interés', TipoColumnaDocumento::Texto));
        $this->assertSame('', $celda(null, TipoColumnaDocumento::Monetario));
    }

    public function test_las_cifras_se_alinean_a_la_derecha_y_lo_demas_a_la_izquierda(): void
    {
        $this->assertSame('right', TipoColumnaDocumento::Monetario->alineacion());
        $this->assertSame('right', TipoColumnaDocumento::Entero->alineacion());
        $this->assertSame('left', TipoColumnaDocumento::Texto->alineacion());
        $this->assertSame('left', TipoColumnaDocumento::Fecha->alineacion());
    }
}
