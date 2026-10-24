<?php

namespace App\Contabilidad\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EditarCentroCostoRequest extends FormRequest
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
                Rule::unique('centros_costo', 'codigo')
                    ->where('empresa_id', $this->user()->empresa_id)
                    ->ignore($this->route('centro')),
            ],
            'nombre' => ['sometimes', 'string', 'max:100'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }
}
