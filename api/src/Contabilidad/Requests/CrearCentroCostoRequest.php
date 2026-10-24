<?php

namespace App\Contabilidad\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CrearCentroCostoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'codigo' => [
                'required', 'string', 'max:20',
                Rule::unique('centros_costo', 'codigo')->where('empresa_id', $this->user()->empresa_id),
            ],
            'nombre' => ['required', 'string', 'max:100'],
        ];
    }
}
