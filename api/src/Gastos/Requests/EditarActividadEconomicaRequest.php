<?php

namespace App\Gastos\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EditarActividadEconomicaRequest extends FormRequest
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
                Rule::unique('actividades_economicas', 'codigo')
                    ->where('empresa_id', $this->user()->empresa_id)
                    ->ignore($this->route('actividad_economica')),
            ],
            'nombre' => ['sometimes', 'string', 'max:150'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }
}
