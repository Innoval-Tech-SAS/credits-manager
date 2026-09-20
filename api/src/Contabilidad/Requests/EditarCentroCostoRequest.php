<?php

namespace App\Contabilidad\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EditarCentroCostoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['sometimes', 'string', 'max:100'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }
}
