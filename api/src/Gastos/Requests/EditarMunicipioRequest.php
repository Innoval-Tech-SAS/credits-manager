<?php

namespace App\Gastos\Requests;

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
        return [
            'codigo' => [
                'sometimes', 'string', 'max:20',
                Rule::unique('municipios', 'codigo')
                    ->where('empresa_id', $this->user()->empresa_id)
                    ->ignore($this->route('municipio')),
            ],
            'nombre' => ['sometimes', 'string', 'max:150'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }
}
