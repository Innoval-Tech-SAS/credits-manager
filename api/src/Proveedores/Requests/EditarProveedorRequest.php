<?php

namespace App\Proveedores\Requests;

use App\Terceros\Enums\TipoDocumentoTercero;
use App\Terceros\Enums\TipoPersona;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EditarProveedorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo_documento' => ['required', Rule::enum(TipoDocumentoTercero::class)],
            'numero_documento' => [
                'required', 'string', 'max:30',
                Rule::unique('terceros', 'numero_documento')
                    ->where('empresa_id', $this->user()->empresa_id)
                    ->where('tipo_documento', $this->input('tipo_documento'))
                    ->ignore($this->route('proveedor')->tercero_id),
            ],
            'nombre' => ['required', 'string', 'max:150'],
            'direccion' => ['nullable', 'string', 'max:200'],
            'barrio' => ['nullable', 'string', 'max:100'],
            'ciudad' => ['nullable', 'string', 'max:100'],
            'celular' => ['nullable', 'string', 'max:30'],
            'telefono_fijo' => ['nullable', 'string', 'max:30'],
            'correo' => ['nullable', 'email', 'max:120'],
            'regimen_tributario' => ['nullable', 'string', 'max:60'],
            'tipo_persona' => ['nullable', Rule::enum(TipoPersona::class)],
            'autorretenedor' => ['sometimes', 'boolean'],
            'responsable_iva' => ['sometimes', 'boolean'],
            'actividad_economica_id' => [
                'nullable',
                Rule::exists('actividades_economicas', 'id')->where('empresa_id', $this->user()->empresa_id),
            ],
            'activo' => ['sometimes', 'boolean'],
        ];
    }
}
