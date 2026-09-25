<?php

namespace App\Terceros\Reglas;

use App\Terceros\Enums\TipoDocumentoTercero;
use App\Terceros\Enums\TipoPersona;
use App\Terceros\Rules\DigitoVerificacionValido;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

/** Reglas de validación por tipo de persona, compartidas por los Requests de terceros, proveedores y clientes. */
class ReglasPersona
{
    /** El tipo de documento de una persona natural lo elige el usuario entre los permitidos. */
    public static function natural(string $presencia = 'required'): array
    {
        return [
            'tipo_documento' => [$presencia, Rule::enum(TipoDocumentoTercero::class)->only(TipoPersona::Natural->documentosPermitidos())],
            'numero_documento' => [$presencia, 'string', 'max:30'],
            'nombres' => [$presencia, 'string', 'max:100'],
            'apellidos' => [$presencia, 'string', 'max:100'],
        ];
    }

    /** El documento de una persona jurídica siempre es NIT: no viaja en el payload, lo fija el Service. */
    public static function juridica(string $presencia = 'required'): array
    {
        return [
            'numero_documento' => [$presencia, 'digits_between:6,15'],
            'razon_social' => [$presencia, 'string', 'max:150'],
            'digito_verificacion' => [$presencia, 'digits:1', new DigitoVerificacionValido],
            'representante_legal' => [$presencia, 'string', 'max:150'],
        ];
    }

    public static function contacto(int $empresaId, bool $obligatorio = false): array
    {
        $presencia = $obligatorio ? 'required' : 'nullable';

        return [
            'direccion' => [$presencia, 'string', 'max:200'],
            'barrio' => ['nullable', 'string', 'max:100'],
            'ciudad' => [$presencia, 'string', 'max:100'],
            'celular' => [$presencia, 'string', 'max:30'],
            'telefono_fijo' => ['nullable', 'string', 'max:30'],
            'correo' => $obligatorio ? ['required', 'email', 'max:150'] : ['nullable', 'email', 'max:120'],
            'regimen_tributario' => ['nullable', 'string', 'max:60'],
            'autorretenedor' => ['sometimes', 'boolean'],
            'responsable_iva' => ['sometimes', 'boolean'],
            'actividad_economica_id' => [
                'nullable',
                Rule::exists('actividades_economicas', 'id')->where('empresa_id', $empresaId),
            ],
        ];
    }

    /** Unicidad del documento por empresa al editar; ignora al propio tercero. */
    public static function documentoUnico(int $empresaId, string $tipoDocumento, int $terceroId): Unique
    {
        return Rule::unique('terceros', 'numero_documento')
            ->where('empresa_id', $empresaId)
            ->where('tipo_documento', $tipoDocumento)
            ->ignore($terceroId);
    }
}
