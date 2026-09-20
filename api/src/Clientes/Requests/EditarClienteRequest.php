<?php

namespace App\Clientes\Requests;

use App\Terceros\Enums\TipoDocumentoTercero;
use App\Terceros\Enums\TipoPersona;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EditarClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:150'],
            'tipo_documento' => ['required', Rule::enum(TipoDocumentoTercero::class)],
            'numero_documento' => [
                'required',
                'string',
                'max:30',
                Rule::unique('terceros', 'numero_documento')
                    ->where('empresa_id', $this->user()->empresa_id)
                    ->where('tipo_documento', $this->input('tipo_documento'))
                    ->ignore($this->route('cliente')->tercero_id),
            ],
            'direccion' => ['required', 'string', 'max:200'],
            'barrio' => ['nullable', 'string', 'max:100'],
            'ciudad' => ['required', 'string', 'max:100'],
            'celular' => ['required', 'string', 'max:30'],
            'telefono_fijo' => ['nullable', 'string', 'max:30'],
            'correo' => ['required', 'email', 'max:150'],
            'empleador_id' => [
                'required', 'integer',
                Rule::exists('empleadores', 'id')->where('empresa_id', $this->user()->empresa_id),
            ],
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
