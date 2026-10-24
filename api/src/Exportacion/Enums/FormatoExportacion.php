<?php

namespace App\Exportacion\Enums;

use App\Exportacion\Contracts\EscritorExportacion;
use App\Exportacion\Services\EscritorCsv;
use App\Exportacion\Services\EscritorXlsx;

/**
 * Punto de extensión: para sumar PDF (Fase 3) se agrega un `case Pdf` y su escritor aquí;
 * ningún reporte cambia, porque solo conocen `FuenteExportable`.
 */
enum FormatoExportacion: string
{
    case Xlsx = 'xlsx';
    case Csv = 'csv';

    public function extension(): string
    {
        return $this->value;
    }

    public function tipoMime(): string
    {
        return match ($this) {
            self::Xlsx => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            self::Csv => 'text/csv; charset=UTF-8',
        };
    }

    /** @return class-string<EscritorExportacion> */
    public function escritor(): string
    {
        return match ($this) {
            self::Xlsx => EscritorXlsx::class,
            self::Csv => EscritorCsv::class,
        };
    }
}
