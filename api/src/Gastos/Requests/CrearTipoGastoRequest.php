<?php

namespace App\Gastos\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CrearTipoGastoRequest extends FormRequest
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
                Rule::unique('tipos_gasto', 'codigo')->where('empresa_id', $this->user()->empresa_id),
            ],
            'nombre' => ['required', 'string', 'max:150'],
            'requiere_tercero' => ['sometimes', 'boolean'],
            'aplica_iva' => ['sometimes', 'boolean'],
        ];
    }
}
