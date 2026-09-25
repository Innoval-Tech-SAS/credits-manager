<?php

namespace App\Terceros\Services;

use App\Terceros\Enums\TipoDocumentoTercero;
use App\Terceros\Enums\TipoPersona;

/** Arma las columnas de `terceros` a partir del payload validado de un Request de persona natural o jurídica. */
class ConstructorDatosTercero
{
    public function construir(TipoPersona $tipoPersona, array $datos): array
    {
        $identidad = match ($tipoPersona) {
            TipoPersona::Natural => [
                'tipo_documento' => $datos['tipo_documento'],
                'nombres' => $datos['nombres'],
                'apellidos' => $datos['apellidos'],
            ],
            TipoPersona::Juridica => [
                'tipo_documento' => TipoDocumentoTercero::Nit->value,
                'razon_social' => $datos['razon_social'],
                'digito_verificacion' => (string) $datos['digito_verificacion'],
                'representante_legal' => $datos['representante_legal'],
            ],
        };

        return [
            ...$identidad,
            'tipo_persona' => $tipoPersona->value,
            'numero_documento' => $datos['numero_documento'],
            'direccion' => $datos['direccion'] ?? null,
            'barrio' => $datos['barrio'] ?? null,
            'ciudad' => $datos['ciudad'] ?? null,
            'celular' => $datos['celular'] ?? null,
            'telefono_fijo' => $datos['telefono_fijo'] ?? null,
            'correo' => $datos['correo'] ?? null,
            'regimen_tributario' => $datos['regimen_tributario'] ?? null,
            'autorretenedor' => $datos['autorretenedor'] ?? false,
            'responsable_iva' => $datos['responsable_iva'] ?? false,
            'actividad_economica_id' => $datos['actividad_economica_id'] ?? null,
        ];
    }
}
