<?php

namespace App\Geografia\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EditarMunicipioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $municipio = $this->route('municipio');

        return [
            'codigo' => [
                'sometimes', 'string', 'max:10',
                Rule::unique('municipios', 'codigo')->where('estado_id', $municipio->estado_id)->ignore($municipio),
            ],
            'nombre' => ['sometimes', 'string', 'max:150'],
            'es_capital' => ['sometimes', 'boolean'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }
}
