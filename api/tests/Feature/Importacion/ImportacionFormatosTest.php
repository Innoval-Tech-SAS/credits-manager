<?php

namespace Tests\Feature\Importacion;

use App\Importacion\Data\FilaRechazada;
use App\Importacion\Enums\TipoImportacion;
use App\Importacion\Lectura\ConvertidorValor;
use App\Importacion\Lectura\FormatoCsvInvalido;
use App\Importacion\Lectura\LectorCsv;
use Tests\TestCase;

class ImportacionFormatosTest extends TestCase
{
    private array $temporales = [];

    protected function tearDown(): void
    {
        foreach ($this->temporales as $ruta) {
            @unlink($ruta);
        }

        parent::tearDown();
    }

    private function archivo(string $contenido): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'imp');
        file_put_contents($ruta, $contenido);
        $this->temporales[] = $ruta;

        return $ruta;
    }

    /** @return list<array{fila: int, valores: array<string, string>}> */
    private function filas(string $contenido, TipoImportacion $tipo = TipoImportacion::Clientes): array
    {
        return iterator_to_array((new LectorCsv($this->archivo($contenido), $tipo))->filas(), false);
    }

    public function test_lee_el_separador_punto_y_coma_con_bom_y_encabezados_con_espacios(): void
    {
        $contenido = "\xEF\xBB\xBF Cédula ; Nombre completo \n53125435;Ana Pérez\n";

        $filas = $this->filas($contenido);

        $this->assertCount(1, $filas);
        $this->assertSame('53125435', $filas[0]['valores']['cedula']);
        $this->assertSame('Ana Pérez', $filas[0]['valores']['nombre']);
    }

    public function test_convierte_windows_1252_a_utf8_en_encabezados_y_valores(): void
    {
        $utf8 = "Cédula;Nombre completo\n1001;José Muñoz\n";
        $windows = mb_convert_encoding($utf8, 'Windows-1252', 'UTF-8');

        $filas = $this->filas($windows);

        $this->assertSame('José Muñoz', $filas[0]['valores']['nombre']);
    }

    public function test_busca_el_encabezado_despues_del_titulo_y_numera_la_fila_del_archivo(): void
    {
        $contenido = "Clientes\nUn cliente por fila.\nCédula;Nombre completo\n1001;Ana Pérez\n";

        $filas = $this->filas($contenido);

        $this->assertSame(4, $filas[0]['fila']);
        $this->assertSame('1001', $filas[0]['valores']['cedula']);
    }

    public function test_omite_las_filas_completamente_vacias(): void
    {
        $contenido = "Cédula;Nombre completo\n1001;Ana Pérez\n;\n\n1002;Luis Ramírez\n";

        $this->assertCount(2, $this->filas($contenido));
    }

    public function test_informa_las_columnas_obligatorias_que_faltan(): void
    {
        $faltantes = (new LectorCsv($this->archivo("Nombre completo;Teléfono\n"), TipoImportacion::Clientes))->encabezadosFaltantes();

        $this->assertSame(['Cédula'], $faltantes);
    }

    public function test_el_archivo_completo_no_pasa_la_validacion_si_no_tiene_encabezados(): void
    {
        $this->expectException(FormatoCsvInvalido::class);

        iterator_to_array((new LectorCsv($this->archivo("1001;Ana\n"), TipoImportacion::Clientes))->filas());
    }

    public function test_acepta_fechas_dd_mm_aaaa_aaaa_mm_dd_y_serial_de_excel(): void
    {
        $this->assertSame('2023-10-14', ConvertidorValor::fecha('14/10/2023', 'f'));
        $this->assertSame('2023-10-14', ConvertidorValor::fecha('2023-10-14', 'f'));
        $this->assertSame('2023-10-14', ConvertidorValor::fecha('45213', 'f'));
    }

    public function test_rechaza_fechas_invalidas_sin_adivinar(): void
    {
        foreach (['31/02/2023', '10.14.2023', '2023/10/14', 'mañana'] as $fecha) {
            try {
                ConvertidorValor::fecha($fecha, 'fecha_desembolso');
                $this->fail("Se esperaba rechazo para {$fecha}.");
            } catch (FilaRechazada $excepcion) {
                $this->assertSame('fecha_desembolso', $excepcion->columna);
            }
        }
    }

    public function test_convierte_montos_en_formato_colombiano_y_con_punto_decimal_a_centavos(): void
    {
        $this->assertSame(200000000, ConvertidorValor::monto('2.000.000', 'valor')->centavos());
        $this->assertSame(200000050, ConvertidorValor::monto('2.000.000,50', 'valor')->centavos());
        $this->assertSame(200000050, ConvertidorValor::monto('2000000.50', 'valor')->centavos());
        $this->assertSame(200000000, ConvertidorValor::monto('$ 2.000.000', 'valor')->centavos());
        $this->assertNull(ConvertidorValor::monto('', 'valor'));
    }

    public function test_rechaza_montos_que_no_se_pueden_leer(): void
    {
        $this->expectException(FilaRechazada::class);

        ConvertidorValor::monto('2,000,000.50.1', 'valor');
    }

    public function test_convierte_respuestas_si_no_y_tasas_con_coma(): void
    {
        $this->assertTrue(ConvertidorValor::siNo('Sí', 'x'));
        $this->assertFalse(ConvertidorValor::siNo('no', 'x'));
        $this->assertSame('2.5', ConvertidorValor::decimal('2,5', 'tasa'));
    }
}
