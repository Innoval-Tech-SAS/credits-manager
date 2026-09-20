<?php

namespace App\Empresas\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EditarEmpresaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre_empresa' => ['required', 'string', 'max:150'],
            'nit' => ['required', 'string', 'max:50'],
            'direccion' => ['required', 'string', 'max:200'],
            'telefono' => ['required', 'string', 'max:50'],
            'correo' => ['required', 'email', 'max:150'],
        ];
    }
}
