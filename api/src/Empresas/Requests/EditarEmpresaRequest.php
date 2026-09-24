<?php

namespace App\Empresas\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'municipio_id' => ['required', 'integer', Rule::exists('municipios', 'id')->where('activo', true)->whereNull('borrado_en')],
            'telefono' => ['required', 'string', 'max:50'],
            'correo' => ['required', 'email', 'max:150'],
        ];
    }
}
