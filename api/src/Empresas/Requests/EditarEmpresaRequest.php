<?php

namespace App\Empresas\Requests;

use App\Terceros\Rules\DigitoVerificacionValido;
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
            'digito_verificacion' => ['required', 'digits:1', new DigitoVerificacionValido('nit', 'empresa.digito_verificacion.invalido')],
            'direccion' => ['required', 'string', 'max:200'],
            'municipio_id' => ['required', 'integer', Rule::exists('municipios', 'id')->where('activo', true)->whereNull('borrado_en')],
            'actividad_economica_id' => ['nullable', 'integer', Rule::exists('actividades_economicas', 'id')->where('empresa_id', $this->empresaEditada())],
            'telefono' => ['required', 'string', 'max:50'],
            'correo' => ['required', 'email', 'max:150'],
        ];
    }

    /** `/empresas/{empresa}` (superadmin) trae la empresa por el route binding; `/empresa` (autoservicio) es la del usuario autenticado. */
    private function empresaEditada(): ?int
    {
        return $this->route('empresa')?->id ?? $this->user()?->empresa_id;
    }
}
