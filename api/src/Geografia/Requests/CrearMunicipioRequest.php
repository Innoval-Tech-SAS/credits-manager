<?php

namespace App\Geografia\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CrearMunicipioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'estado_id' => ['required', 'integer', Rule::exists('estados', 'id')->where('activo', true)->whereNull('borrado_en')],
            'codigo' => [
                'required', 'string', 'max:10',
                Rule::unique('municipios', 'codigo')->where('estado_id', $this->input('estado_id')),
            ],
            'nombre' => ['required', 'string', 'max:150'],
            'es_capital' => ['sometimes', 'boolean'],
        ];
    }
}
