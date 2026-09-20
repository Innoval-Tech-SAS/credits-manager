<?php

namespace App\Contabilidad\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EditarPlanCuentaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['sometimes', 'string', 'max:150'],
            'permite_movimiento' => ['sometimes', 'boolean'],
            'activa' => ['sometimes', 'boolean'],
        ];
    }
}
