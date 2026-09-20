<?php

namespace App\Exportacion\Services;

use App\Exportacion\Constants\ExportacionConstants;
use App\Exportacion\Contracts\EscritorExportacion;
use App\Exportacion\Data\ColumnaExportable;
use App\Exportacion\Data\ParametrosExportacion;
use App\Exportacion\Enums\TipoColumna;
use LogicException;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\BooleanCell;
use OpenSpout\Common\Entity\Cell\DateTimeCell;
use OpenSpout\Common\Entity\Cell\EmptyCell;
use OpenSpout\Common\Entity\Cell\NumericCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Hoja "Datos" limpia (encabezado + filas, para que el contador pueda cruzarla) y, al cerrar,
 * la hoja "Parámetros". Montos, fechas y booleanos se escriben como celdas tipadas, nunca como texto.
 */
class EscritorXlsx implements EscritorExportacion
{
    private ?Writer $escritor = null;

    /** @var list<ColumnaExportable> */
    private array $columnas = [];

    private ParametrosExportacion $parametros;

    /** @var array<string, Style> */
    private array $estilos = [];

    public function __construct(private readonly ConvertidorValores $convertidor) {}

    public function abrir(string $rutaDestino, ParametrosExportacion $parametros, array $columnas): void
    {
        $this->columnas = $columnas;
        $this->parametros = $parametros;
        $this->estilos = $this->construirEstilos();

        $this->escritor = new Writer;
        $this->escritor->openToFile($rutaDestino);
        $this->escritor->getCurrentSheet()->setName(ExportacionConstants::HOJA_DATOS);

        $estiloEncabezado = (new Style)->withFontBold(true)->withBackgroundColor('E5E7EB');

        $this->escritor->addRow(new Row(array_map(
            fn (ColumnaExportable $columna) => new StringCell($this->convertidor->neutralizar($columna->encabezado), $estiloEncabezado),
            $columnas,
        )));
    }

    public function escribirFila(array $fila): void
    {
        $this->escritorAbierto()->addRow(new Row(array_map(
            fn (ColumnaExportable $columna) => $this->celda($columna, $fila[$columna->clave] ?? null),
            $this->columnas,
        )));
    }

    public function cerrar(): void
    {
        $escritor = $this->escritorAbierto();

        $escritor->addNewSheetAndMakeItCurrent()->setName(ExportacionConstants::HOJA_PARAMETROS);

        $negrita = (new Style)->withFontBold(true);
        $parametros = $this->parametros;

        $filas = [
            ['Reporte', $parametros->titulo],
            ['Empresa', $parametros->empresa],
            ['Generado por', $parametros->generadoPor],
            ['Fecha y hora', $parametros->generadoEn->format('Y-m-d H:i:s')],
            ['Filas exportadas', (string) $parametros->filas],
            ['Filtros', $parametros->filtros === [] ? 'Sin filtros' : ''],
        ];

        foreach ($parametros->filtros as $nombre => $valor) {
            $filas[] = ['  '.$nombre, (string) $valor];
        }

        foreach ($filas as [$etiqueta, $valor]) {
            $escritor->addRow(new Row([
                new StringCell($etiqueta, $negrita),
                new StringCell($this->convertidor->neutralizar($valor)),
            ]));
        }

        $escritor->close();
        $this->escritor = null;
    }

    private function celda(ColumnaExportable $columna, mixed $valor): Cell
    {
        $estilo = $this->estilos[$columna->tipo->value] ?? null;

        try {
            $celda = match ($columna->tipo) {
                TipoColumna::Texto => ($texto = $this->convertidor->texto($valor)) === null ? null : new StringCell($this->convertidor->neutralizar($texto), $estilo),
                TipoColumna::Entero => ($entero = $this->convertidor->entero($valor)) === null ? null : new NumericCell($entero, $estilo),
                TipoColumna::Decimal => ($decimal = $this->convertidor->decimal($valor)) === null ? null : new NumericCell($decimal, $estilo),
                TipoColumna::Monetario => ($monto = $this->convertidor->monetarioComoFloat($valor)) === null ? null : new NumericCell($monto, $estilo),
                TipoColumna::Porcentaje => ($porcentaje = $this->convertidor->decimal($valor)) === null ? null : new NumericCell($porcentaje / 100, $estilo),
                TipoColumna::Fecha, TipoColumna::FechaHora => ($fecha = $this->convertidor->fecha($valor)) === null ? null : new DateTimeCell($fecha, $estilo),
                TipoColumna::Booleano => ($booleano = $this->convertidor->booleano($valor)) === null ? null : new BooleanCell($booleano, $estilo),
            };
        } catch (\InvalidArgumentException $excepcion) {
            throw new LogicException("Columna '{$columna->clave}': ".$excepcion->getMessage(), previous: $excepcion);
        }

        return $celda ?? new EmptyCell(null);
    }

    /** @return array<string, Style> */
    private function construirEstilos(): array
    {
        return [
            TipoColumna::Entero->value => (new Style)->withFormat('0'),
            TipoColumna::Decimal->value => (new Style)->withFormat(ExportacionConstants::FORMATO_DECIMAL),
            TipoColumna::Monetario->value => (new Style)->withFormat(ExportacionConstants::FORMATO_MONETARIO),
            TipoColumna::Porcentaje->value => (new Style)->withFormat(ExportacionConstants::FORMATO_PORCENTAJE),
            TipoColumna::Fecha->value => (new Style)->withFormat(ExportacionConstants::FORMATO_FECHA),
            TipoColumna::FechaHora->value => (new Style)->withFormat(ExportacionConstants::FORMATO_FECHA_HORA),
        ];
    }

    private function escritorAbierto(): Writer
    {
        return $this->escritor ?? throw new LogicException('El escritor XLSX no está abierto.');
    }
}
