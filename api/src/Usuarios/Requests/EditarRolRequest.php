<?php

namespace App\Usuarios\Requests;

use App\Usuarios\Models\Permiso;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EditarRolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:50', Rule::unique('roles', 'nombre')->where('empresa_id', $this->user()->empresa_id)->ignore($this->route('rol'))],
            'descripcion' => ['nullable', 'string', 'max:200'],
            'permisos' => ['sometimes', 'array'],
            'permisos.*' => ['integer', Rule::in($this->idsPermisosValidos())],
        ];
    }

    /** Evita una query `exists` por elemento del array (una por cada id de permiso enviado). */
    private function idsPermisosValidos(): array
    {
        return Permiso::query()->pluck('id')->all();
    }
}
