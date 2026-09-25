<?php

namespace App\Impresion\Data;

use App\Terceros\Models\Tercero;

/** A quién va dirigido el documento (bloque "Señores"): los campos sin dato no se imprimen. */
final readonly class DestinatarioDocumento
{
    public function __construct(
        public string $nombre,
        public ?string $documento = null,
        public ?string $telefono = null,
        public ?string $direccion = null,
        public ?string $ciudad = null,
        public string $etiquetaDocumento = 'NIT',
    ) {}

    /** `nombre` ya es la razón social o el nombre completo; el NIT sale con su dígito de verificación. */
    public static function desdeTercero(Tercero $tercero): self
    {
        return new self(
            nombre: $tercero->nombre,
            documento: $tercero->documentoConDigito(),
            telefono: $tercero->celular ?: $tercero->telefono_fijo,
            direccion: $tercero->direccion,
            ciudad: $tercero->ciudad,
            etiquetaDocumento: strtoupper($tercero->tipo_documento->value),
        );
    }
}
