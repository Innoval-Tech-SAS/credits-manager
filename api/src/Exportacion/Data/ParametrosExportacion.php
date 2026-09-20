<?php

namespace App\Exportacion\Data;

use Carbon\CarbonImmutable;

/** Contenido de la hoja "Parámetros": quién, cuándo y con qué filtros se generó el archivo. */
final readonly class ParametrosExportacion
{
    /** @param array<string, scalar|null> $filtros */
    public function __construct(
        public string $titulo,
        public string $empresa,
        public string $generadoPor,
        public CarbonImmutable $generadoEn,
        public int $filas,
        public array $filtros,
    ) {}
}
