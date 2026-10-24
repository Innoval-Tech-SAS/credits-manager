<?php

namespace App\Importacion\Procesadores;

use App\Importacion\Data\ContextoEjecucion;

interface ProcesadorFila
{
    /** @param list<array<string, string>> $valoresLote  Consulta de referencias para todo el lote en pocas consultas. */
    public function precargar(array $valoresLote, ContextoEjecucion $ctx): void;

    /** @param array<string, string> $valores  Lanza FilaRechazada, EmpleadorPendiente o ValidationException; con `escribir` false no escribe. */
    public function procesar(array $valores, ContextoEjecucion $ctx): void;
}
