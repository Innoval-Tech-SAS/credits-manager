<?php

namespace App\Empleadores\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CrearEmpleadorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => [
                'required', 'string', 'max:150',
                Rule::unique('empleadores', 'nombre')->where('empresa_id', $this->user()->empresa_id),
            ],
            'nit' => ['nullable', 'string', 'max:50'],
            'direccion' => ['nullable', 'string', 'max:200'],
            'telefono' => ['nullable', 'string', 'max:30'],
        ];
    }
}
