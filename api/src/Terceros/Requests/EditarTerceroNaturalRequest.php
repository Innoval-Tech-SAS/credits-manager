<?php

namespace App\Terceros\Requests;

use App\Terceros\Reglas\ReglasPersona;
use Illuminate\Foundation\Http\FormRequest;

class EditarTerceroNaturalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $reglas = ReglasPersona::natural('sometimes');
        $reglas['numero_documento'][] = ReglasPersona::documentoUnico($this->user()->empresa_id, $this->input('tipo_documento', $this->route('tercero')->tipo_documento->value), $this->route('tercero')->id);

        return [
            ...$reglas,
            ...ReglasPersona::contacto($this->user()->empresa_id),
            'activo' => ['sometimes', 'boolean'],
        ];
    }
}
