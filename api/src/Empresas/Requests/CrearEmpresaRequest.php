<?php

namespace App\Empresas\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class CrearEmpresaRequest extends FormRequest
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
            'admin_nombre' => ['required', 'string', 'max:100'],
            'admin_usuario' => ['required', 'string', 'max:50', 'unique:usuarios,usuario'],
            'admin_password' => ['required', 'string', Password::min(8)->letters()->numbers()->symbols()],
        ];
    }
}
