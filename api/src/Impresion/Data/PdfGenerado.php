<?php

namespace App\Impresion\Data;

/** El PDF ya armado y el nombre con el que se descarga (sin extensión). */
final readonly class PdfGenerado
{
    public function __construct(
        public string $contenido,
        public string $nombreArchivo,
    ) {}
}
