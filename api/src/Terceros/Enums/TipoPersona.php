<?php

namespace App\Terceros\Enums;

enum TipoPersona: string
{
    case Natural = 'natural';
    case Juridica = 'juridica';

    /**
     * Documentos válidos por tipo de persona. Sin excepción para una persona natural con NIT (RUT):
     * si el negocio la necesita, se agrega aquí como decisión explícita del usuario y no por inferencia.
     *
     * @return array<int, TipoDocumentoTercero>
     */
    public function documentosPermitidos(): array
    {
        return match ($this) {
            self::Natural => [TipoDocumentoTercero::Cc, TipoDocumentoTercero::Ce, TipoDocumentoTercero::Pasaporte],
            self::Juridica => [TipoDocumentoTercero::Nit],
        };
    }
}
