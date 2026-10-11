<?php

namespace App\Empleadores\Requests;

use App\Terceros\Rules\DigitoVerificacionValido;
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
            'digito_verificacion' => ['nullable', 'digits:1', new DigitoVerificacionValido('nit', 'empleador.digito_verificacion.invalido')],
            'direccion' => ['nullable', 'string', 'max:200'],
            'telefono' => ['nullable', 'string', 'max:30'],
        ];
    }
}
