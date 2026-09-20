<?php

namespace App\Exportacion\Contracts;

use App\Exportacion\Data\ColumnaExportable;
use App\Exportacion\Data\ParametrosExportacion;

/** Escribe un archivo en streaming (memoria constante). Solo los escritores conocen la librería. */
interface EscritorExportacion
{
    /** @param list<ColumnaExportable> $columnas */
    public function abrir(string $rutaDestino, ParametrosExportacion $parametros, array $columnas): void;

    /** @param array<string, mixed> $fila indexada por clave de columna */
    public function escribirFila(array $fila): void;

    public function cerrar(): void;
}
