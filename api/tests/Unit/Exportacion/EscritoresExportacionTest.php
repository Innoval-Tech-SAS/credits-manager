<?php

namespace Tests\Unit\Exportacion;

use App\Core\Dinero\Dinero;
use App\Exportacion\Data\ColumnaExportable;
use App\Exportacion\Data\ParametrosExportacion;
use App\Exportacion\Enums\TipoColumna;
use App\Exportacion\Services\EscritorCsv;
use App\Exportacion\Services\EscritorXlsx;
use Carbon\CarbonImmutable;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

class EscritoresExportacionTest extends TestCase
{
    private string $archivo;

    protected function setUp(): void
    {
        parent::setUp();

        // La suite corre en UTC; los archivos deben salir en la zona horaria del proyecto.
        config(['app.timezone' => 'America/Bogota']);

        $this->archivo = tempnam(sys_get_temp_dir(), 'exp');
    }

    protected function tearDown(): void
    {
        @unlink($this->archivo);

        parent::tearDown();
    }

    /** @return list<ColumnaExportable> */
    private function columnas(): array
    {
        return [
            new ColumnaExportable('t', 'Texto', TipoColumna::Texto),
            new ColumnaExportable('n', 'Entero', TipoColumna::Entero),
            new ColumnaExportable('d', 'Decimal', TipoColumna::Decimal),
            new ColumnaExportable('m', 'Monto', TipoColumna::Monetario),
            new ColumnaExportable('p', 'Porcentaje', TipoColumna::Porcentaje),
            new ColumnaExportable('f', 'Fecha', TipoColumna::Fecha),
            new ColumnaExportable('h', 'Fecha y hora', TipoColumna::FechaHora),
            new ColumnaExportable('b', 'Booleano', TipoColumna::Booleano),
        ];
    }

    private function parametros(): ParametrosExportacion
    {
        return new ParametrosExportacion('Reporte de pruebas', 'Empresa Uno SAS', 'Ana Pérez', CarbonImmutable::parse('2026-03-15 10:30:00'), 3, ['periodo' => '2026-03', 'estado' => '=cmd']);
    }

    private function filas(): array
    {
        return [
            ['t' => 'Hola', 'n' => 7, 'd' => '3.14159', 'm' => Dinero::desdePesos('1234567.89'), 'p' => '12.5', 'f' => '2026-03-15', 'h' => CarbonImmutable::parse('2026-03-15 15:30:00', 'UTC'), 'b' => true],
            ['t' => '=HYPERLINK("http://malo","x")', 'n' => 0, 'd' => 2, 'm' => '-500.5', 'p' => 0, 'f' => CarbonImmutable::parse('2026-01-02'), 'h' => '2026-01-02 08:00:00', 'b' => false],
            ['t' => '+1+1', 'n' => null, 'd' => null, 'm' => null, 'p' => null, 'f' => null, 'h' => null, 'b' => null],
            ['t' => '-2', 'n' => 1, 'd' => 1, 'm' => 0, 'p' => 1, 'f' => '2026-01-01', 'h' => '2026-01-01 00:00:00', 'b' => true],
            ['t' => '@SUM(A1)', 'n' => 1, 'd' => 1, 'm' => 1, 'p' => 1, 'f' => '2026-01-01', 'h' => '2026-01-01 00:00:00', 'b' => true],
            ['t' => "\t=1+1", 'n' => 1, 'd' => 1, 'm' => 1, 'p' => 1, 'f' => '2026-01-01', 'h' => '2026-01-01 00:00:00', 'b' => true],
            ['t' => 'Texto normal con = en medio', 'n' => 1, 'd' => 1, 'm' => 1, 'p' => 1, 'f' => '2026-01-01', 'h' => '2026-01-01 00:00:00', 'b' => true],
        ];
    }

    private function escribir(string $clase): void
    {
        $escritor = app($clase);
        $escritor->abrir($this->archivo, $this->parametros(), $this->columnas());

        foreach ($this->filas() as $fila) {
            $escritor->escribirFila($fila);
        }

        $escritor->cerrar();
    }

    /** @return array<string, list<list<mixed>>> hoja => filas */
    private function leerXlsx(): array
    {
        $lector = new Reader;
        $lector->open($this->archivo);

        $hojas = [];

        foreach ($lector->getSheetIterator() as $hoja) {
            foreach ($hoja->getRowIterator() as $fila) {
                $hojas[$hoja->getName()][] = $fila->toArray();
            }
        }

        $lector->close();

        return $hojas;
    }

    public function test_xlsx_tiene_hoja_de_datos_limpia_y_hoja_de_parametros(): void
    {
        $this->escribir(EscritorXlsx::class);

        $hojas = $this->leerXlsx();

        $this->assertSame(['Datos', 'Parámetros'], array_keys($hojas));
        $this->assertSame(['Texto', 'Entero', 'Decimal', 'Monto', 'Porcentaje', 'Fecha', 'Fecha y hora', 'Booleano'], $hojas['Datos'][0]);
        $this->assertCount(1 + count($this->filas()), $hojas['Datos']);

        $parametros = collect($hojas['Parámetros'])->mapWithKeys(fn ($fila) => [trim($fila[0]) => $fila[1] ?? null]);

        $this->assertSame('Reporte de pruebas', $parametros['Reporte']);
        $this->assertSame('Empresa Uno SAS', $parametros['Empresa']);
        $this->assertSame('Ana Pérez', $parametros['Generado por']);
        $this->assertSame('2026-03-15 10:30:00', $parametros['Fecha y hora']);
        $this->assertSame('2026-03', $parametros['periodo']);
        $this->assertSame("'=cmd", $parametros['estado'], 'También los filtros de la hoja Parámetros se neutralizan.');
    }

    public function test_xlsx_escribe_montos_y_numeros_como_celdas_numericas_no_como_texto(): void
    {
        $this->escribir(EscritorXlsx::class);

        $fila = $this->leerXlsx()['Datos'][1];

        $this->assertSame(7, $fila[1]);
        $this->assertEqualsWithDelta(3.14159, $fila[2], 1e-9);
        $this->assertIsFloat($fila[3]);
        $this->assertEqualsWithDelta(1234567.89, $fila[3], 1e-6);
        $this->assertEqualsWithDelta(0.125, $fila[4], 1e-9, 'El porcentaje se guarda como fracción con formato de porcentaje.');
        $this->assertTrue($fila[7]);

        $negativa = $this->leerXlsx()['Datos'][2];
        $this->assertEqualsWithDelta(-500.5, $negativa[3], 1e-9, 'Una cadena decimal se convierte en número, nunca queda como texto.');
    }

    public function test_xlsx_escribe_fechas_como_fechas_en_america_bogota(): void
    {
        $this->escribir(EscritorXlsx::class);

        $fila = $this->leerXlsx()['Datos'][1];

        $this->assertInstanceOf(\DateTimeInterface::class, $fila[5]);
        $this->assertSame('2026-03-15', $fila[5]->format('Y-m-d'));
        $this->assertInstanceOf(\DateTimeInterface::class, $fila[6]);
        $this->assertSame('2026-03-15 10:30:00', $fila[6]->format('Y-m-d H:i:s'), '15:30 UTC son las 10:30 en Bogotá.');
    }

    public function test_xlsx_neutraliza_formulas_en_texto_y_conserva_el_resto(): void
    {
        $this->escribir(EscritorXlsx::class);

        $datos = $this->leerXlsx()['Datos'];

        $this->assertSame('Hola', $datos[1][0]);
        $this->assertSame("'=HYPERLINK(\"http://malo\",\"x\")", $datos[2][0]);
        $this->assertSame("'+1+1", $datos[3][0]);
        $this->assertSame("'-2", $datos[4][0]);
        $this->assertSame("'@SUM(A1)", $datos[5][0]);
        $this->assertSame("'\t=1+1", $datos[6][0]);
        $this->assertSame('Texto normal con = en medio', $datos[7][0]);
    }

    public function test_xlsx_deja_vacias_las_celdas_nulas(): void
    {
        $this->escribir(EscritorXlsx::class);

        $fila = $this->leerXlsx()['Datos'][3];

        foreach ([1, 2, 3, 4, 5, 6, 7] as $indice) {
            $this->assertEmpty($fila[$indice] ?? null, "La columna {$indice} debía quedar vacía.");
        }
    }

    public function test_csv_es_utf8_con_bom_y_escribe_montos_como_decimal_plano(): void
    {
        $this->escribir(EscritorCsv::class);

        $contenido = file_get_contents($this->archivo);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $contenido);

        $filas = array_map('str_getcsv', explode("\n", trim(substr($contenido, 3))));

        $this->assertSame(['Texto', 'Entero', 'Decimal', 'Monto', 'Porcentaje', 'Fecha', 'Fecha y hora', 'Booleano'], $filas[0]);
        $this->assertSame(['Hola', '7', '3.14159', '1234567.89', '12.5', '2026-03-15', '2026-03-15 10:30:00', 'Sí'], $filas[1]);
        $this->assertSame('-500.50', $filas[2][3]);
        $this->assertSame('No', $filas[2][7]);
        $this->assertSame("'+1+1", $filas[3][0]);
        $this->assertSame(['', '', '', '', '', '', ''], array_slice($filas[3], 1));
    }

    public function test_csv_neutraliza_formulas_en_texto(): void
    {
        $this->escribir(EscritorCsv::class);

        $filas = array_map('str_getcsv', explode("\n", trim(substr(file_get_contents($this->archivo), 3))));

        $this->assertSame("'=HYPERLINK(\"http://malo\",\"x\")", $filas[2][0]);
        $this->assertSame("'+1+1", $filas[3][0]);
        $this->assertSame("'-2", $filas[4][0]);
        $this->assertSame("'@SUM(A1)", $filas[5][0]);
        $this->assertSame('Texto normal con = en medio', $filas[7][0]);
    }

    public function test_un_monto_no_numerico_falla_en_lugar_de_escribir_basura(): void
    {
        $escritor = app(EscritorXlsx::class);
        $escritor->abrir($this->archivo, $this->parametros(), [new ColumnaExportable('m', 'Monto', TipoColumna::Monetario)]);

        $this->expectException(\LogicException::class);

        $escritor->escribirFila(['m' => 'no-es-un-numero']);
    }
}
