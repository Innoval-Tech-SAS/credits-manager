<?php

namespace App\Empresas\Requests;

use App\Terceros\Rules\DigitoVerificacionValido;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
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
            'digito_verificacion' => ['required', 'digits:1', new DigitoVerificacionValido('nit', 'empresa.digito_verificacion.invalido')],
            'direccion' => ['required', 'string', 'max:200'],
            'municipio_id' => ['required', 'integer', Rule::exists('municipios', 'id')->where('activo', true)->whereNull('borrado_en')],
            'actividad_economica_codigo' => ['nullable', 'string', 'max:20', 'required_with:actividad_economica_nombre'],
            'actividad_economica_nombre' => ['nullable', 'string', 'max:150', 'required_with:actividad_economica_codigo'],
            'responsable_iva' => ['nullable', 'boolean'],
            'autorretenedor' => ['nullable', 'boolean'],
            'telefono' => ['required', 'string', 'max:50'],
            'correo' => ['required', 'email', 'max:150'],
            'admin_nombre' => ['required', 'string', 'max:100'],
            'admin_usuario' => ['required', 'string', 'max:50', 'unique:usuarios,usuario'],
            'admin_password' => ['required', 'string', Password::min(8)->letters()->numbers()->symbols()],
        ];
    }
}
