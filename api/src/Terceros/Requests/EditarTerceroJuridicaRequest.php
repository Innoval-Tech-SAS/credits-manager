<?php

namespace App\Terceros\Requests;

use App\Terceros\Enums\TipoDocumentoTercero;
use App\Terceros\Reglas\ReglasPersona;
use Illuminate\Foundation\Http\FormRequest;

class EditarTerceroJuridicaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $reglas = ReglasPersona::juridica('sometimes');
        $reglas['numero_documento'][] = ReglasPersona::documentoUnico($this->user()->empresa_id, TipoDocumentoTercero::Nit->value, $this->route('tercero')->id);

        return [
            ...$reglas,
            ...ReglasPersona::contacto($this->user()->empresa_id),
            'activo' => ['sometimes', 'boolean'],
        ];
    }
}
