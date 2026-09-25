<?php

namespace App\Usuarios\Requests;

use App\Usuarios\Models\Permiso;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class CrearUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:100'],
            'usuario' => ['required', 'string', 'max:50', 'unique:usuarios,usuario'],
            'clave' => ['required', 'string', Password::min(8)->letters()->numbers()->symbols()],
            'rol_id' => ['required', 'integer', Rule::exists('roles', 'id')->where('empresa_id', $this->user()->empresa_id)],
            'activo' => ['required', 'boolean'],
            'es_global' => ['sometimes', 'boolean'],
            'permisos_extra' => ['sometimes', 'array'],
            'permisos_extra.*.permiso_id' => ['required', 'integer', Rule::in($this->idsPermisosValidos())],
            'permisos_extra.*.concedido' => ['required', 'boolean'],
        ];
    }

    /** Evita una query `exists` por elemento del array (una por cada permiso_id enviado). */
    private function idsPermisosValidos(): array
    {
        return Permiso::query()->pluck('id')->all();
    }
}
