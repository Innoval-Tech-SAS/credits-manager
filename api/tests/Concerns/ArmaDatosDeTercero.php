<?php

namespace Tests\Concerns;

use App\Terceros\Enums\TipoDocumentoTercero;
use App\Terceros\Enums\TipoPersona;
use App\Terceros\Services\DigitoVerificacionNit;

/** Datos coherentes de persona natural o jurídica para crear terceros directamente en tests. */
trait ArmaDatosDeTercero
{
    /** Con `nombre` se parte en nombres y apellidos (natural) o se usa como razón social (jurídica), para que el nombre derivado quede igual. */
    protected function datosDePersona(TipoPersona $tipoPersona, string $nombre, string $numeroDocumento): array
    {
        if ($tipoPersona === TipoPersona::Juridica) {
            return [
                'tipo_persona' => TipoPersona::Juridica,
                'tipo_documento' => TipoDocumentoTercero::Nit,
                'numero_documento' => $numeroDocumento,
                'razon_social' => $nombre,
                'digito_verificacion' => (string) DigitoVerificacionNit::calcular($numeroDocumento),
                'representante_legal' => 'Representante de prueba',
            ];
        }

        [$nombres, $apellidos] = array_pad(explode(' ', $nombre, 2), 2, null);

        return [
            'tipo_persona' => TipoPersona::Natural,
            'tipo_documento' => TipoDocumentoTercero::Cc,
            'numero_documento' => $numeroDocumento,
            'nombres' => $nombres,
            'apellidos' => $apellidos,
        ];
    }
}
