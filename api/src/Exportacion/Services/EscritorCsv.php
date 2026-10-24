<?php

namespace App\Exportacion\Services;

use App\Exportacion\Contracts\EscritorExportacion;
use App\Exportacion\Data\ColumnaExportable;
use App\Exportacion\Data\ParametrosExportacion;
use App\Exportacion\Enums\TipoColumna;
use LogicException;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\CSV\Options;
use OpenSpout\Writer\CSV\Writer;

/** CSV UTF-8 con BOM (Excel lo abre bien). Montos como decimal plano de 2 cifras, fechas ISO en America/Bogota. */
class EscritorCsv implements EscritorExportacion
{
    private ?Writer $escritor = null;

    /** @var list<ColumnaExportable> */
    private array $columnas = [];

    public function __construct(private readonly ConvertidorValores $convertidor) {}

    public function abrir(string $rutaDestino, ParametrosExportacion $parametros, array $columnas): void
    {
        $this->columnas = $columnas;

        $this->escritor = new Writer(new Options(SHOULD_ADD_BOM: true));
        $this->escritor->openToFile($rutaDestino);

        $this->escritor->addRow(new Row(array_map(
            fn (ColumnaExportable $columna) => new StringCell($this->convertidor->neutralizar($columna->encabezado), null),
            $columnas,
        )));
    }

    public function escribirFila(array $fila): void
    {
        $this->escritorAbierto()->addRow(new Row(array_map(
            fn (ColumnaExportable $columna) => new StringCell($this->valor($columna, $fila[$columna->clave] ?? null), null),
            $this->columnas,
        )));
    }

    public function cerrar(): void
    {
        $this->escritorAbierto()->close();
        $this->escritor = null;
    }

    private function valor(ColumnaExportable $columna, mixed $valor): string
    {
        $texto = match ($columna->tipo) {
            TipoColumna::Texto => ($t = $this->convertidor->texto($valor)) === null ? null : $this->convertidor->neutralizar($t),
            TipoColumna::Entero => $this->convertidor->entero($valor),
            TipoColumna::Decimal, TipoColumna::Porcentaje => $this->convertidor->decimal($valor),
            TipoColumna::Monetario => $this->convertidor->monetarioComoTexto($valor),
            TipoColumna::Fecha => $this->convertidor->fecha($valor)?->format('Y-m-d'),
            TipoColumna::FechaHora => $this->convertidor->fecha($valor)?->format('Y-m-d H:i:s'),
            TipoColumna::Booleano => ($b = $this->convertidor->booleano($valor)) === null ? null : ($b ? 'Sí' : 'No'),
        };

        return $texto === null ? '' : (string) $texto;
    }

    private function escritorAbierto(): Writer
    {
        return $this->escritor ?? throw new LogicException('El escritor CSV no está abierto.');
    }
}
