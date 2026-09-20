<?php

namespace App\Gastos\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EditarTipoGastoRequest extends FormRequest
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
                Rule::unique('tipos_gasto', 'codigo')
                    ->where('empresa_id', $this->user()->empresa_id)
                    ->ignore($this->route('tipo_gasto')),
            ],
            'nombre' => ['sometimes', 'string', 'max:150'],
            'requiere_tercero' => ['sometimes', 'boolean'],
            'aplica_iva' => ['sometimes', 'boolean'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }
}
