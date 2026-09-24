<?php

namespace App\Impresion\Data;

/** A quién va dirigido el documento (bloque "Señores"): los campos sin dato no se imprimen. */
final readonly class DestinatarioDocumento
{
    public function __construct(
        public string $nombre,
        public ?string $documento = null,
        public ?string $telefono = null,
        public ?string $direccion = null,
        public ?string $ciudad = null,
    ) {}
}
