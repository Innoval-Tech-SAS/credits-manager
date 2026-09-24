<?php

namespace App\Impresion\Data;

/** Bloque de texto bajo la tabla: valor en letras, forma de pago, observaciones… */
final readonly class SeccionDocumento
{
    public function __construct(
        public string $titulo,
        public string $texto,
    ) {}
}
